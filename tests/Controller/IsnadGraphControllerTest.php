<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'endpoint doit restituer le graphe exactement tel qu'il est décrit dans le
 * jeu de données versionné : la vue Réseau consomme cette forme sans
 * transformation, donc tout écart casserait le rendu.
 *
 * Le test compare la réponse au fichier source plutôt qu'à des valeurs
 * recopiées : il attrape les pertes d'information à l'import (c'est ainsi
 * qu'a été trouvé le fait que bio/work/region dépendent du hadith).
 */
final class IsnadGraphControllerTest extends WebTestCase
{
    use PreparesHadithDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::primeHadithDatabase(static::getContainer()->get(EntityManagerInterface::class));
    }

    public function testGraphMatchesDataset(): void
    {
        $payload = $this->fetchGraph();
        $dataset = $this->dataset();

        self::assertSame(array_keys($dataset['periods']), array_keys($payload['periods']));

        foreach ($dataset['hadiths'] as $slug => $expected) {
            if (!isset($expected['rawis'], $expected['links'])) {
                continue;
            }

            self::assertArrayHasKey($slug, $payload['hadiths']);
            $actual = $payload['hadiths'][$slug];

            // L'ordre des nœuds n'est pas un contrat : le graphe les indexe par
            // clé. Seul l'ensemble compte.
            $expectedPeople = array_keys($expected['rawis']);
            $actualPeople = array_keys($actual['rawis']);
            sort($expectedPeople);
            sort($actualPeople);

            self::assertSame(
                $expectedPeople,
                $actualPeople,
                \sprintf('Les transmetteurs de « %s » diffèrent.', $slug),
            );

            self::assertSame(
                self::normaliseLinks($expected['links']),
                self::normaliseLinks($actual['links']),
                \sprintf('Les arêtes de « %s » diffèrent.', $slug),
            );

            foreach ($expected['rawis'] as $person => $rawi) {
                foreach (['lvl', 'name', 'ar', 'gen', 'meta', 'region', 'role', 'bio', 'work'] as $field) {
                    if (!\array_key_exists($field, $rawi)) {
                        continue;
                    }
                    self::assertSame(
                        $rawi[$field],
                        $actual['rawis'][$person][$field] ?? null,
                        \sprintf('%s/%s.%s', $slug, $person, $field),
                    );
                }
            }
        }
    }

    public function testPivotIsFlaggedWithItsChainCount(): void
    {
        $intention = $this->fetchGraph()['hadiths']['intention'];

        self::assertSame('yahya', $intention['pivot']);
        self::assertTrue($intention['rawis']['yahya']['pivot']);
        self::assertSame('64', $intention['rawis']['yahya']['chains']);
        // Le pivot n'est pas marqué ailleurs.
        self::assertArrayNotHasKey('pivot', $intention['rawis']['umar']);
    }

    /**
     * Les relations amont/aval ne sont pas stockées : elles se déduisent des arêtes.
     */
    public function testNeighboursAreDerivedFromEdges(): void
    {
        $rawis = $this->fetchGraph()['hadiths']['intention']['rawis'];

        self::assertSame('Muhammad ibn Ibrâhîm al-Taymî', $rawis['yahya']['up']);
        self::assertStringContainsString('Mâlik ibn Anas', $rawis['yahya']['down']);
        // La racine n'a pas d'amont.
        self::assertArrayNotHasKey('up', $rawis['prophet']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchGraph(): array
    {
        $this->client->request('GET', '/api/isnad');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{periods: array<string, mixed>, hadiths: array<string, mixed>}
     */
    private function dataset(): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__, 2).'/data/isnad/wireframe.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Ramène chaque arête à [source, cible, gharîb] pour comparer sans dépendre
     * de la présence du troisième élément, optionnel dans la source.
     *
     * @param list<array<int, mixed>> $links
     *
     * @return list<array{string, string, bool}>
     */
    private static function normaliseLinks(array $links): array
    {
        $normalised = array_map(
            static fn (array $link): array => [(string) $link[0], (string) $link[1], (bool) ($link[2] ?? false)],
            $links,
        );
        sort($normalised);

        return $normalised;
    }
}
