<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Collection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Collection>
 */
final class CollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Collection::class);
    }

    public function findOneByTitleKey(string $titleKey): ?Collection
    {
        return $this->findOneBy(['titleKey' => $titleKey]);
    }

    public function findOneBySlug(string $slug): ?Collection
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Collection>
     */
    public function findOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
