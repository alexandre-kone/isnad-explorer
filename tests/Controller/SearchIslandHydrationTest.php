<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Hydratation réelle de l'îlot Stimulus « search » (AD-8) : Panther pilote un
 * vrai navigateur et prouve que le bouton « Effacer » n'apparaît qu'après
 * hydratation, puis vide le champ au clic.
 */
final class SearchIslandHydrationTest extends PantherTestCase
{
    use PreparesHadithDatabase;

    protected function setUp(): void
    {
        self::bootKernel();
        self::primeHadithDatabase(static::getContainer()->get(EntityManagerInterface::class));
        self::ensureKernelShutdown();
    }

    public function testClearButtonHydratesAndClearsInput(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/recherche?q=intention');

        // Contrat SSR : le formulaire porte le contrôleur Stimulus.
        self::assertSelectorExists('[data-controller="search"]');

        // Preuve d'hydratation : connect() marque l'élément.
        self::assertSelectorAttributeWillContain('[data-controller="search"]', 'data-hydrated', 'true');

        // Le champ est pré-rempli → le bouton « Effacer » devient visible.
        $client->waitForVisibility('[data-search-target="clear"]');

        // Au clic, clear() vide le champ ; toggleClear() re-cache alors le bouton.
        $client->findElement(WebDriverBy::cssSelector('[data-search-target="clear"]'))->click();
        $client->waitForInvisibility('[data-search-target="clear"]');

        $value = $client->executeScript('return document.querySelector(\'[data-search-target="input"]\').value;');
        self::assertSame('', $value);
    }
}
