<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\PersonMerger;
use App\Domain\PersonNameNormaliser;
use App\Entity\Enum\NameKind;
use App\Entity\Enum\NameScript;
use App\Entity\HadithCluster;
use App\Entity\Riwaya;
use App\Entity\Person;
use App\Entity\PersonMergeLog;
use App\Entity\PersonName;
use App\Entity\Transmission;
use App\Repository\PeriodRepository;
use App\Repository\PersonRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La fusion est l'opération la plus destructrice de l'outil, et son piège n'est
 * pas le repointage mais les collisions qu'il provoque.
 */
final class PersonMergerTest extends KernelTestCase
{
    use PreparesHadithDatabase;

    private EntityManagerInterface $em;
    private PersonNameNormaliser $normaliser;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->normaliser = new PersonNameNormaliser();
        self::primeHadithDatabase($this->em);
    }

    /**
     * A → C et B → C existent ; fusionner B dans A produirait deux fois A → C,
     * ce que la contrainte d'unicité refuse. Une seule arête doit survivre.
     */
    public function testCollidingEdgesAreMergedNotDuplicated(): void
    {
        [$riwaya, $a, $b, $c] = $this->scenarioWithCollision();

        static::getContainer()->get(PersonMerger::class)->merge($b, $a, null);
        $this->em->clear();

        $edges = $this->em->getRepository(Transmission::class)->findBy(['riwaya' => $riwaya->getId()]);

        self::assertCount(1, $edges, 'Les deux arêtes vers C doivent avoir fusionné.');
        self::assertSame($a->getId(), $edges[0]->getFrom()->getId());
        // L'arête survivante hérite du caractère gharîb.
        self::assertTrue($edges[0]->isSpine());
    }

    public function testAbsorbedPersonDisappearsAndNamesAreTransferred(): void
    {
        [, $a, $b] = $this->scenarioWithCollision();
        $absorbedSlug = $b->getSlug();

        static::getContainer()->get(PersonMerger::class)->merge($b, $a, null);
        $this->em->clear();

        $people = static::getContainer()->get(PersonRepository::class);

        self::assertNull($people->findOneBySlug($absorbedSlug));

        $kept = $people->findOneBySlug($a->getSlug());
        $forms = array_map(static fn (PersonName $n): string => $n->getForm(), $kept->getNames()->toArray());

        self::assertContains('Forme de B', $forms, 'Les formes de la fiche absorbée doivent être reprises.');
    }

    /**
     * Une seule forme d'affichage par écriture : celle reprise ne doit pas
     * entrer en concurrence avec celle de la fiche conservée.
     */
    public function testTransferredNameDoesNotBecomeASecondDisplayForm(): void
    {
        [, $a, $b] = $this->scenarioWithCollision();

        static::getContainer()->get(PersonMerger::class)->merge($b, $a, null);
        $this->em->clear();

        $kept = static::getContainer()->get(PersonRepository::class)->findOneBySlug($a->getSlug());

        $displays = array_filter(
            $kept->getNames()->toArray(),
            static fn (PersonName $n): bool => $n->isDisplay() && NameScript::Latin === $n->getScript(),
        );

        self::assertCount(1, $displays);
    }

    public function testMergeIsJournalled(): void
    {
        [, $a, $b] = $this->scenarioWithCollision();

        $log = static::getContainer()->get(PersonMerger::class)->merge($b, $a, null);

        self::assertInstanceOf(PersonMergeLog::class, $log);
        self::assertSame($b->getSlug(), $log->getAbsorbedSlug());
        self::assertNotEmpty($log->getTransferredNames());
        self::assertSame(1, $log->getImpact()['collisions']);
    }

    public function testPreviewReportsImpactWithoutChangingAnything(): void
    {
        [$riwaya, $a, $b] = $this->scenarioWithCollision();

        $before = \count($this->em->getRepository(Transmission::class)->findBy(['riwaya' => $riwaya->getId()]));
        $impact = static::getContainer()->get(PersonMerger::class)->preview($b, $a);
        $after = \count($this->em->getRepository(Transmission::class)->findBy(['riwaya' => $riwaya->getId()]));

        self::assertSame($before, $after, 'L\'aperçu ne doit rien modifier.');
        self::assertSame(1, $impact['transmissions']);
        self::assertSame(1, $impact['collisions']);
    }

    public function testMergingAPersonWithItselfIsRefused(): void
    {
        [, $a] = $this->scenarioWithCollision();

        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(PersonMerger::class)->merge($a, $a, null);
    }

    /**
     * Construit un cas contrôlé : deux fiches distinctes transmettant au même
     * élève, dans la même riwāya.
     *
     * @return array{Riwaya, Person, Person, Person}
     */
    private function scenarioWithCollision(): array
    {
        $period = static::getContainer()->get(PeriodRepository::class)->findOrdered()[2];

        $a = $this->person('fusion-a', 'Forme de A', $period);
        $b = $this->person('fusion-b', 'Forme de B', $period);
        $c = $this->person('fusion-c', 'Forme de C', $period);

        $cluster = new HadithCluster('fusion-test', 'Hadith de test');
        $riwaya = new Riwaya($cluster, 'fusion-test', 'Texte', 'Réf. de test');
        $cluster->addRiwaya($riwaya);
        $riwaya->addParticipant($a, 1);
        $riwaya->addParticipant($b, 1);
        $riwaya->addParticipant($c, 2);
        $riwaya->addTransmission($a, $c, false);
        $riwaya->addTransmission($b, $c, true); // celle-ci porte l'épine

        $this->em->persist($cluster);
        $this->em->persist($riwaya);
        $this->em->flush();

        return [$riwaya, $a, $b, $c];
    }

    private function person(string $slug, string $form, \App\Entity\Period $period): Person
    {
        $person = new Person($slug, $period);
        $name = new PersonName(
            $person,
            $form,
            $this->normaliser->normalise($form, NameScript::Latin),
            NameScript::Latin,
            NameKind::Complete,
        );
        $name->setDisplay(true);
        $person->addName($name);

        $this->em->persist($name);
        $this->em->persist($person);

        return $person;
    }
}
