<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Repository\PersonRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Horodatage, attribution et verrou optimiste des entités curatées.
 */
final class CuratedEntityTest extends WebTestCase
{
    use PreparesHadithDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::primeHadithDatabase($this->em);
    }

    /**
     * Les données chargées par import ne sont créées par aucun curateur :
     * l'attribution reste nulle, ce qui est exact et non une lacune.
     */
    public function testImportedDataIsTimestampedButUnattributed(): void
    {
        $person = $this->people()->findOneBySlug('yahya');

        self::assertNotNull($person);
        self::assertNotNull($person->getCreatedAt());
        self::assertNull($person->getCreatedBy());
    }

    public function testWriteByASignedInCuratorIsAttributed(): void
    {
        $curator = $this->curator('scribe@example.test');
        $this->client->loginUser($curator);

        $person = $this->people()->findOneBySlug('yahya');
        $person->setRegion('Médine · Koufa · révisé');
        $this->em->flush();

        self::assertSame($curator->getId(), $person->getUpdatedBy()?->getId());
        self::assertNotNull($person->getUpdatedAt());
    }

    /**
     * Deux curateurs modifient la même fiche : le second enregistrement échoue
     * au lieu d'écraser silencieusement le premier.
     */
    public function testConcurrentWriteIsRejected(): void
    {
        $person = $this->people()->findOneBySlug('malik');
        $id = $person->getId();

        // Un autre curateur enregistre entre-temps, hors de cet EntityManager.
        $this->em->getConnection()->executeStatement(
            'UPDATE person SET region = ?, version = version + 1 WHERE id = ?',
            ['modifié ailleurs', $id],
        );

        $person->setRegion('modifié ici');

        $this->expectException(OptimisticLockException::class);
        $this->em->flush();
    }

    /**
     * Le cas réel : la version voyage par le formulaire entre deux requêtes.
     * Sans cet aller-retour, la colonne « version » ne protège rien.
     */
    public function testStaleVersionFromAFormIsDetected(): void
    {
        $person = $this->people()->findOneBySlug('malik');
        $versionAffichee = $person->getVersion(); // ce que porterait le champ caché

        $this->em->getConnection()->executeStatement(
            'UPDATE person SET region = ?, version = version + 1 WHERE id = ?',
            ['modifié ailleurs', $person->getId()],
        );

        // La requête suivante relit la fiche, désormais en version supérieure.
        $this->em->clear();
        $rechargee = $this->people()->findOneBySlug('malik');
        self::assertGreaterThan($versionAffichee, $rechargee->getVersion());

        $this->expectException(OptimisticLockException::class);
        $this->em->lock($rechargee, \Doctrine\DBAL\LockMode::OPTIMISTIC, $versionAffichee);
    }

    private function people(): PersonRepository
    {
        return static::getContainer()->get(PersonRepository::class);
    }

    private function curator(string $email): User
    {
        $curator = new User($email, 'Scribe');
        $curator->setRoles(['ROLE_ADMIN'])
            ->setPassword(
                static::getContainer()->get(UserPasswordHasherInterface::class)
                    ->hashPassword($curator, 'motdepasse'),
            );

        $this->em->persist($curator);
        $this->em->flush();

        return $curator;
    }
}
