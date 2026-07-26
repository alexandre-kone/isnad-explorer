<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Accès à l'espace de curation : le pare-feu, le rôle et le curateur désactivé.
 */
final class AdminAccessTest extends WebTestCase
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

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/connexion');
    }

    public function testLoginPageIsPubliclyReachable(): void
    {
        $this->client->request('GET', '/admin/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testActiveCuratorSignsIn(): void
    {
        $this->curator('actif@example.test', active: true);

        $this->client->request('GET', '/admin/connexion');
        $this->client->submitForm('Se connecter', [
            '_username' => 'actif@example.test',
            '_password' => 'motdepasse',
        ]);

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Espace de curation');
    }

    /**
     * Un curateur désactivé conserve ses attributions mais ne peut plus entrer.
     */
    public function testDisabledCuratorIsRefused(): void
    {
        $this->curator('inactif@example.test', active: false);

        $this->client->request('GET', '/admin/connexion');
        $this->client->submitForm('Se connecter', [
            '_username' => 'inactif@example.test',
            '_password' => 'motdepasse',
        ]);

        $this->client->followRedirect();
        self::assertSelectorExists('[data-testid="login-error"]');
        self::assertSelectorTextContains('[data-testid="login-error"]', 'désactivé');
    }

    private function curator(string $email, bool $active): User
    {
        $curator = new User($email, 'Curateur de test');
        $curator->setRoles(['ROLE_ADMIN'])
            ->setActive($active)
            ->setPassword(
                static::getContainer()->get(UserPasswordHasherInterface::class)
                    ->hashPassword($curator, 'motdepasse'),
            );

        $this->em->persist($curator);
        $this->em->flush();

        return $curator;
    }
}
