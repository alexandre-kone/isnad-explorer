<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Un segment de citation extrait d'une référence composite.
 *
 * « Sahîh al-Bukhârî, n°13 · Muslim, n°45 » en produit deux.
 */
final readonly class Citation
{
    public function __construct(
        public string $title,
        /** Clé d'identité du recueil : c'est elle qui rapproche « Muslim » de « Sahîh Muslim ». */
        public string $titleKey,
        public ?string $number,
    ) {
    }
}
