<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
{
    use PreparesHadithDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::primeHadithDatabase(static::getContainer()->get(EntityManagerInterface::class));
    }

    public function testSearchReturnsHadithWithItsIsnad(): void
    {
        $this->client->request('GET', '/recherche', ['q' => 'intention']);

        self::assertResponseIsSuccessful();
        // Le matn recherché est rendu…
        self::assertSelectorTextContains('blockquote', 'Les actes ne valent que par les intentions');
        // …avec sa référence et son isnad ordonné (Compagnon → maître du compilateur).
        self::assertSelectorTextContains('body', 'Sahih al-Bukhari 1');
        self::assertSelectorTextContains('body', 'Umar ibn al-Khattab');
        self::assertSelectorTextContains('body', 'Abdullah ibn al-Zubayr al-Humaydi');
        self::assertSelectorExists('[data-testid="result-count"]');
    }

    public function testEmptyQueryShowsPromptAndNoResults(): void
    {
        $crawler = $this->client->request('GET', '/recherche');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Saisissez un terme');
        self::assertCount(0, $crawler->filter('blockquote'));
    }

    public function testUnknownTermShowsNoResults(): void
    {
        $this->client->request('GET', '/recherche', ['q' => 'zzzznotfound']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="no-results"]');
    }
}
