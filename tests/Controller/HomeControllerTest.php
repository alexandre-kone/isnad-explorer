<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomeRendersDomainMessageAndIslandWiring(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Isnad Explorer');
        self::assertSelectorTextContains('body', 'domaine relié'); // service Domain injecté (AD-11)
        self::assertSelectorExists('[data-controller="hello"]'); // island Stimulus câblé (AD-8)
    }
}
