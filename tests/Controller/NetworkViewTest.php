<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Hydratation réelle de la vue Réseau : Panther pilote un vrai navigateur et
 * prouve que le graphe se construit à partir de /api/isnad.
 *
 * Le rendu SSR n'est qu'une coquille vide — sans ce test, une régression dans
 * l'îlot (import vis-network cassé, cible manquante) passerait inaperçue.
 */
final class NetworkViewTest extends PantherTestCase
{
    use PreparesHadithDatabase;

    protected function setUp(): void
    {
        self::bootKernel();
        self::primeHadithDatabase(static::getContainer()->get(EntityManagerInterface::class));
        self::ensureKernelShutdown();
    }

    public function testGraphHydratesAndRendersPivotFiche(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/reseau');

        // Contrat SSR : la coquille porte l'îlot.
        self::assertSelectorExists('[data-controller="network"]');

        // Preuve d'hydratation : connect() marque l'élément une fois les
        // données chargées.
        self::assertSelectorAttributeWillContain('[data-controller="network"]', 'data-hydrated', 'true');

        // vis-network dessine dans un <canvas> injecté dans la scène.
        $client->waitForVisibility('#net canvas');

        // Les filtres sont peuplés depuis les générations de l'API.
        $client->waitFor('[data-network-target="genChips"] .chip');
        self::assertGreaterThan(
            1,
            \count($client->findElements(WebDriverBy::cssSelector('[data-network-target="genChips"] .chip'))),
        );

        // La fiche s'ouvre d'office sur le pivot du hadith affiché.
        $client->waitFor('[data-network-target="fiche"] .band');
        self::assertSelectorTextContains('[data-network-target="fiche"]', 'Yahyâ ibn Saʿîd al-Ansârî');
        // Panther lit le texte rendu : .pivotflag est en text-transform:uppercase,
        // d'où l'assertion sur la partie arabe, insensible à la casse.
        self::assertSelectorTextContains('[data-network-target="fiche"] .pivotflag', 'مدار');
    }

    public function testNarratorSearchFocusesTheNode(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/reseau');
        self::assertSelectorAttributeWillContain('[data-controller="network"]', 'data-hydrated', 'true');
        $client->waitFor('[data-network-target="fiche"] .band');

        $client->findElement(WebDriverBy::cssSelector('[data-network-target="search"]'))->sendKeys('Malik');
        $client->waitForVisibility('[data-network-target="results"] .res');

        $client->findElement(WebDriverBy::cssSelector('[data-network-target="results"] .res'))->click();

        // La fiche bascule sur le narrateur choisi.
        self::assertSelectorTextContains('[data-network-target="fiche"]', 'Mâlik ibn Anas');
    }

    public function testCollectionChipIsolatesAWay(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/reseau');
        self::assertSelectorAttributeWillContain('[data-controller="network"]', 'data-hydrated', 'true');
        // Attendre le cadrage automatique sur le pivot avant d'agir : sinon on
        // teste une course plutôt que le comportement.
        $client->waitFor('[data-network-target="fiche"] .band');
        $client->waitFor('[data-network-target="colChips"] .chip');

        $chip = $client->findElement(WebDriverBy::cssSelector('[data-network-target="colChips"] .chip'));
        $chip->click();

        // La pastille du recueil isolé passe à l'état actif.
        $client->waitFor('[data-network-target="colChips"] .chip.act');
        self::assertSelectorExists('[data-network-target="colChips"] .chip.act');
    }
}
