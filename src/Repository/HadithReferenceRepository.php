<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Collection;
use App\Entity\HadithReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HadithReference>
 */
final class HadithReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HadithReference::class);
    }

    /**
     * @return list<HadithReference>
     */
    public function findByCollection(Collection $collection): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('h')
            ->join('r.hadith', 'h')
            ->where('r.collection = :collection')
            ->setParameter('collection', $collection)
            ->orderBy('h.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> Nombre de références, indexé par identifiant de recueil
     */
    public function countByCollection(): array
    {
        /** @var list<array{collection: int, total: int}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.collection) AS collection', 'COUNT(r.id) AS total')
            ->groupBy('r.collection')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['collection']] = (int) $row['total'];
        }

        return $counts;
    }
}
