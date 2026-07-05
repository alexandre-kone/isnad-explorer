<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Service Domain de démonstration (Story 1.1) — prouve la couche Domain injectée
 * dans les contrôleurs (AD-11). Aucune logique métier réelle ici.
 */
final class HelloService
{
    public function message(): string
    {
        return 'Isnad Explorer v2 — domaine relié.';
    }
}
