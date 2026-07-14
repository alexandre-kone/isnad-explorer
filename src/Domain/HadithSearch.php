<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Hadith;
use App\Repository\HadithRepository;

/**
 * Service Domain (AD-11) : recherche de hadiths par leur texte (matn) et
 * exposition de leur isnad. Point d'entrée métier injecté dans le contrôleur.
 */
final class HadithSearch
{
    public function __construct(private readonly HadithRepository $hadiths)
    {
    }

    /**
     * Recherche par matn. Un terme vide (ou uniquement des espaces) ne
     * retourne rien — pas de listing par défaut.
     *
     * @return list<Hadith>
     */
    public function byMatn(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return $this->hadiths->searchByMatn($term);
    }
}
