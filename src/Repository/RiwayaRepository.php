<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Riwaya;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Riwaya>
 */
final class RiwayaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Riwaya::class);
    }

    /**
     * Recherche les occurrences dont le texte français contient le terme
     * (insensible à la casse), graphe de transmission préchargé.
     *
     * La limite s'applique bien aux riwāyāt (et non aux lignes jointes du
     * graphe) : {@see Paginator} pagine sur les entités racines tout en
     * hydratant l'intégralité des arêtes.
     *
     * @return list<Riwaya>
     */
    public function searchByMatn(string $term, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('r')
            ->leftJoin('r.cluster', 'c')->addSelect('c')
            ->leftJoin('r.transmissions', 't')->addSelect('t')
            ->leftJoin('t.from', 'pf')->addSelect('pf')
            ->leftJoin('t.to', 'pt')->addSelect('pt')
            ->where("LOWER(r.textFr) LIKE :term ESCAPE '\\'")
            ->setParameter('term', '%'.self::escapeLike(mb_strtolower($term)).'%')
            ->orderBy('r.reference', 'ASC')
            ->setMaxResults($limit)
            ->getQuery();

        return array_values(iterator_to_array(new Paginator($query, fetchJoinCollection: true)));
    }

    /**
     * Neutralise les métacaractères LIKE (\ % _) d'un terme saisi par
     * l'utilisateur pour qu'ils soient traités littéralement.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
