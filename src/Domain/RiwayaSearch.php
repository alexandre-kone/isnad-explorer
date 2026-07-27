<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Riwaya;
use App\Repository\RiwayaRepository;

/**
 * Service Domain (AD-11) : recherche d'occurrences par leur texte (matn) et
 * exposition de leur isnad. Point d'entrée métier injecté dans le contrôleur.
 *
 * La recherche porte sur la riwāya et non sur l'enseignement : c'est le texte
 * de la voie qui est saisi, et deux voies du même hadith peuvent différer.
 */
final class RiwayaSearch
{
    public function __construct(private readonly RiwayaRepository $riwayat)
    {
    }

    /**
     * Un terme vide (ou uniquement des espaces) ne retourne rien — pas de
     * listing par défaut.
     *
     * @return list<Riwaya>
     */
    public function byMatn(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return $this->riwayat->searchByMatn($term);
    }
}
