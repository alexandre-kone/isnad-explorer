<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Écriture d'une forme de nom. Une personne a une forme d'affichage par
 * écriture : la translittération pour le latin, l'arabe pour l'arabe.
 */
enum NameScript: string
{
    case Latin = 'latin';
    case Arabic = 'ar';

    public function label(): string
    {
        return match ($this) {
            self::Latin => 'Translittération',
            self::Arabic => 'Arabe',
        };
    }
}
