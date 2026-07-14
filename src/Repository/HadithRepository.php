<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Hadith;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hadith>
 */
final class HadithRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hadith::class);
    }

    /**
     * Recherche les hadiths dont le matn contient le terme (insensible à la
     * casse), isnad préchargé et ordonné.
     *
     * La limite s'applique bien aux hadiths (et non aux lignes jointes de
     * l'isnad) : {@see Paginator} pagine sur les entités racines tout en
     * hydratant l'intégralité de chaque chaîne (l'ordre des maillons est
     * garanti par le #[ORM\OrderBy] de Hadith::$isnadLinks).
     *
     * @return list<Hadith>
     */
    public function searchByMatn(string $term, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('h')
            ->leftJoin('h.isnadLinks', 'l')->addSelect('l')
            ->leftJoin('l.narrator', 'n')->addSelect('n')
            ->where("LOWER(h.matn) LIKE :term ESCAPE '\\'")
            ->setParameter('term', '%'.self::escapeLike(mb_strtolower($term)).'%')
            ->orderBy('h.reference', 'ASC')
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
