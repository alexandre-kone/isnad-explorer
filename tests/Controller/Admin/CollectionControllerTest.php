<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\BibliographyImporter;
use App\Domain\ReferenceParser;
use App\Entity\Collection;
use App\Entity\User;
use App\Repository\CollectionRepository;
use App\Repository\HadithRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CollectionControllerTest extends WebTestCase
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

    public function testBibliographyScreensRequireAuthentication(): void
    {
        $this->client->request('GET', '/admin/recueils');

        self::assertResponseRedirects('/admin/connexion');
    }

    public function testIndexListsTheCollectionsFoundInTheCorpus(): void
    {
        $this->signIn();

        $this->client->request('GET', '/admin/recueils');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="collection-list"]', 'Sahîh al-Bukhârî');
        self::assertSelectorTextContains('[data-testid="collection-list"]', 'Sahîh Muslim');
        self::assertSelectorTextContains('[data-testid="collection-list"]', 'Abû Dâwûd');
    }

    public function testCollectionSheetListsWhatItReports(): void
    {
        $this->signIn();

        $this->client->request('GET', '/admin/recueils/bukhari');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="reference-list"]', 'Hadith de l\'intention');
        self::assertSelectorTextContains('[data-testid="reference-list"]', 'n°1');
        self::assertSelectorExists('[data-testid="no-edition"]');
    }

    public function testUnknownCollectionIsNotFound(): void
    {
        $this->signIn();

        $this->client->request('GET', '/admin/recueils/introuvable');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Le test qui prouve la découpe : s'il manque une référence, une citation a
     * été perdue en silence.
     */
    public function testEveryCitedSegmentBecameAStructuredReference(): void
    {
        $parser = new ReferenceParser();
        $hadiths = static::getContainer()->get(HadithRepository::class)->findAll();

        self::assertNotEmpty($hadiths);

        foreach ($hadiths as $hadith) {
            $expected = $parser->parse($hadith->getReference());
            $actual = $hadith->getReferences();

            self::assertCount(
                \count($expected),
                $actual,
                \sprintf('« %s » : autant de références que de segments cités.', $hadith->getSlug()),
            );

            foreach ($expected as $position => $citation) {
                $reference = $actual[$position];
                self::assertSame($citation->number, $reference->getNumber());
                self::assertSame($citation->titleKey, $reference->getCollection()->getTitleKey());
            }

            self::assertTrue($actual[0]->isPrimary());
        }
    }

    public function testImportingTheSameCorpusTwiceAddsNothing(): void
    {
        $importer = new BibliographyImporter();
        $hadiths = static::getContainer()->get(HadithRepository::class)->findAll();

        foreach ($hadiths as $hadith) {
            self::assertSame([], $importer->import($this->em, $hadith));
        }

        $this->em->flush();

        $collections = static::getContainer()->get(CollectionRepository::class)->findOrdered();
        self::assertCount(3, $collections);
    }

    /** Le corpus cite « Muslim » et « Sahîh Muslim » : une seule ligne en base. */
    public function testTwoFormsOfATitleGiveASingleCollection(): void
    {
        $collections = static::getContainer()->get(CollectionRepository::class)->findOrdered();

        $keys = array_map(static fn (Collection $c) => $c->getTitleKey(), $collections);

        self::assertSame($keys, array_unique($keys), 'Un recueil ne doit exister qu\'une fois.');
        self::assertContains('muslim', $keys);
        self::assertCount(3, $collections);
    }

    private function signIn(): void
    {
        $curator = new User('bibliographie@example.test', 'Curateur');
        $curator->setRoles(['ROLE_ADMIN'])->setPassword('peu-importe');
        $this->em->persist($curator);
        $this->em->flush();

        $this->client->loginUser($curator);
    }
}
