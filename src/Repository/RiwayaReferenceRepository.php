<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Collection;
use App\Entity\RiwayaReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiwayaReference>
 */
final class RiwayaReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiwayaReference::class);
    }

    /**
     * @return list<RiwayaReference>
     */
    public function findByCollection(Collection $collection): array
    {
        return $this->createQueryBuilder('ref')
            ->addSelect('r', 'c')
            ->join('ref.riwaya', 'r')
            ->join('r.cluster', 'c')
            ->where('ref.collection = :collection')
            ->setParameter('collection', $collection)
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> Nombre de références, indexé par identifiant de recueil
     */
    public function countByCollection(): array
    {
        /** @var list<array{collection: int, total: int}> $rows */
        $rows = $this->createQueryBuilder('ref')
            ->select('IDENTITY(ref.collection) AS collection', 'COUNT(ref.id) AS total')
            ->groupBy('ref.collection')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['collection']] = (int) $row['total'];
        }

        return $counts;
    }
}
