<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Component\Panther\PantherTestCase;

/**
 * Test d'hydratation réelle de l'island Stimulus (AD-8).
 *
 * Contrairement au WebTestCase (qui n'exécute pas le JS et ne vérifie donc que
 * la présence de l'attribut côté serveur), Panther pilote un vrai navigateur :
 * il prouve que l'importmap sert le contrôleur et que Stimulus l'hydrate.
 */
final class HomeIslandHydrationTest extends PantherTestCase
{
    public function testStimulusIslandHydratesOnHomePage(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        // Contrat SSR : l'attribut est rendu côté serveur.
        self::assertSelectorExists('[data-controller="hello"]');

        // Preuve d'hydratation : Stimulus exécute connect() une fois le JS chargé.
        // Les assertions « Will » attendent l'exécution du JS avant de trancher.
        self::assertSelectorAttributeWillContain('[data-controller="hello"]', 'data-hydrated', 'true');
        self::assertSelectorWillContain('[data-controller="hello"]', 'JS hydraté');
    }
}
