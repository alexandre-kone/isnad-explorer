<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\HadithRepository;
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
        // …avec sa référence et son épine gharîb, du Prophète ﷺ jusqu'au pivot.
        self::assertSelectorTextContains('body', 'Sahîh al-Bukhârî, n°1');
        self::assertSelectorTextContains('body', 'ʿUmar ibn al-Khattâb');
        self::assertSelectorTextContains('body', 'Yahyâ ibn Saʿîd al-Ansârî');
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

    /**
     * La limite s'applique aux hadiths (pas aux arêtes jointes du graphe) : un
     * hadith renvoyé conserve l'intégralité de sa transmission, même avec
     * limit = 1 alors que plusieurs hadiths correspondent.
     */
    public function testLimitCountsHadithsAndKeepsFullGraph(): void
    {
        $repository = static::getContainer()->get(HadithRepository::class);

        // « isl » correspond à plusieurs hadiths (îmân, ihsân…).
        self::assertGreaterThan(1, \count($repository->searchByMatn('isl', 20)));

        $results = $repository->searchByMatn('isl', 1);

        self::assertCount(1, $results);
        // Le graphe du hadith retenu est complet, pas tronqué par la limite.
        self::assertGreaterThan(1, $results[0]->getTransmissions()->count());
        self::assertNotEmpty($results[0]->getSpine());
    }

    /**
     * Les métacaractères LIKE saisis sont traités littéralement : « % » ne
     * matche pas tout.
     */
    public function testLikeWildcardIsTreatedLiterally(): void
    {
        $repository = static::getContainer()->get(HadithRepository::class);

        self::assertSame([], $repository->searchByMatn('%', 20));
    }
}
