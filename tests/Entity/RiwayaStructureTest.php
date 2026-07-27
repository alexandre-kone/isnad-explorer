<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\HadithCluster;
use App\Repository\HadithClusterRepository;
use App\Repository\RiwayaRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Ce que la scission a déplacé : le texte et la chaîne appartiennent à
 * l'occurrence, l'enseignement ne porte plus que ce qui vaut pour toutes ses
 * voies.
 */
final class RiwayaStructureTest extends WebTestCase
{
    use PreparesHadithDatabase;

    protected function setUp(): void
    {
        static::createClient();
        self::primeHadithDatabase(static::getContainer()->get(EntityManagerInterface::class));
    }

    public function testTheImportedCorpusGivesOneRiwayaPerCluster(): void
    {
        $clusters = static::getContainer()->get(HadithClusterRepository::class)->findAll();

        self::assertNotEmpty($clusters);

        foreach ($clusters as $cluster) {
            self::assertCount(
                1,
                $cluster->getRiwayat(),
                \sprintf('« %s » : le jeu de données ne distingue pas les voies.', $cluster->getSlug()),
            );
            self::assertSame($cluster->getPrimaryRiwaya(), $cluster->getRiwayat()->first());
        }
    }

    public function testTheMatnAndTheChainBelongToTheRiwaya(): void
    {
        $riwaya = static::getContainer()->get(RiwayaRepository::class)->findOneBy(['slug' => 'intention']);

        self::assertNotNull($riwaya);
        self::assertStringContainsString('intentions', $riwaya->getTextFr());
        self::assertSame('Sahîh al-Bukhârî, n°1', $riwaya->getReference());
        self::assertNotEmpty($riwaya->getParticipants());
        self::assertNotEmpty($riwaya->getTransmissions());
        self::assertNotEmpty($riwaya->getSpine());
        self::assertSame('yahya', $riwaya->getPivot()?->getSlug());
    }

    public function testTheClusterKeepsWhatHoldsForEveryPath(): void
    {
        $cluster = static::getContainer()->get(HadithClusterRepository::class)->findOneBy(['slug' => 'intention']);

        self::assertNotNull($cluster);
        self::assertSame('Hadith de l\'intention', $cluster->getLabel());
        self::assertNotNull($cluster->getTuruq());

        // Le mappage — donc la table — ne porte plus rien qui varie d'une voie
        // à l'autre. C'est ce qui attraperait une colonne texte réintroduite
        // sur l'enseignement.
        $fields = static::getContainer()->get(EntityManagerInterface::class)
            ->getClassMetadata(HadithCluster::class)
            ->getFieldNames();

        self::assertSame(
            [],
            array_values(array_intersect(['textFr', 'textAr', 'reference', 'grade'], $fields)),
            'Le texte, la citation et le grade appartiennent à la riwāya.',
        );
    }

    public function testTheStructuredReferencesFollowedTheRiwaya(): void
    {
        $riwaya = static::getContainer()->get(RiwayaRepository::class)->findOneBy(['slug' => 'ukhuwwa']);

        self::assertNotNull($riwaya);
        self::assertCount(2, $riwaya->getReferences());

        foreach ($riwaya->getReferences() as $reference) {
            self::assertSame($riwaya, $reference->getRiwaya());
        }
    }
}
