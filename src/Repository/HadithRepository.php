<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Hadith;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return list<Hadith>
     */
    public function searchByMatn(string $term, int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.isnadLinks', 'l')->addSelect('l')
            ->leftJoin('l.narrator', 'n')->addSelect('n')
            ->where('LOWER(h.matn) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
            ->orderBy('h.reference', 'ASC')
            ->addOrderBy('l.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
