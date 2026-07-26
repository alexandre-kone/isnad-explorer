<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\PersonNameNormaliser;
use App\Domain\PersonSearch;
use App\Entity\Enum\NameKind;
use App\Entity\Enum\NameScript;
use App\Entity\Person;
use App\Entity\PersonName;
use App\Repository\PeriodRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersonSearchTest extends KernelTestCase
{
    use PreparesHadithDatabase;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::primeHadithDatabase($this->em);
    }

    /**
     * Le cas emblématique : deux formes du même homme sans aucun caractère
     * commun. Si la recherche les sépare, le curateur crée un doublon.
     */
    public function testKunyaAndIsmReachTheSamePerson(): void
    {
        $this->personWithNames('abuHanifa', [
            ['Abū Ḥanīfa', NameScript::Latin, NameKind::Kunya, true],
            ['al-Nuʿmān ibn Thābit', NameScript::Latin, NameKind::Ism, false],
            ['أبو حنيفة', NameScript::Arabic, NameKind::Kunya, true],
        ]);

        $search = static::getContainer()->get(PersonSearch::class);

        $parKunya = $search->search('Abu Hanifa');
        $parIsm = $search->search('al-Numan ibn Thabit');

        self::assertCount(1, $parKunya);
        self::assertCount(1, $parIsm);
        self::assertSame(
            $parKunya[0]['person']->getSlug(),
            $parIsm[0]['person']->getSlug(),
        );
    }

    public function testSearchIgnoresDiacriticsAndCase(): void
    {
        $search = static::getContainer()->get(PersonSearch::class);

        // « Yahyâ ibn Saʿîd al-Ansârî » est dans le jeu importé.
        $results = $search->search('yahya ibn said');

        self::assertNotEmpty($results);
        self::assertSame('yahya', $results[0]['person']->getSlug());
    }

    public function testArabicTermFindsTheArabicForm(): void
    {
        $search = static::getContainer()->get(PersonSearch::class);

        // Saisi sans tashkīl, alors que la fiche le porte.
        $results = $search->search('مالك بن انس');

        self::assertNotEmpty($results);
        self::assertSame('malik', $results[0]['person']->getSlug());
    }

    /**
     * Le contexte accompagne chaque résultat : sans lui, deux homonymes sont
     * indiscernables dans la liste.
     */
    public function testResultsCarryDiscriminatingContext(): void
    {
        $results = static::getContainer()->get(PersonSearch::class)->search('yahya ibn said');

        self::assertNotSame('', $results[0]['context']);
    }

    public function testEmptyTermReturnsNothing(): void
    {
        self::assertSame([], static::getContainer()->get(PersonSearch::class)->search('   '));
    }

    /**
     * @param list<array{0: string, 1: NameScript, 2: NameKind, 3: bool}> $forms
     */
    private function personWithNames(string $slug, array $forms): Person
    {
        $normaliser = new PersonNameNormaliser();
        $period = static::getContainer()->get(PeriodRepository::class)->findOrdered()[2];

        $person = new Person($slug, $period);
        foreach ($forms as [$form, $script, $kind, $display]) {
            $name = new PersonName($person, $form, $normaliser->normalise($form, $script), $script, $kind);
            $name->setDisplay($display);
            $person->addName($name);
            $this->em->persist($name);
        }

        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }
}
