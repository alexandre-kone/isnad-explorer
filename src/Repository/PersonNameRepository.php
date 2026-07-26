<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PersonName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonName>
 */
final class PersonNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonName::class);
    }

    /**
     * Formes normalisées partagées par plusieurs personnes : le signal fort de
     * doublon, deux fiches se réclamant du même nom.
     *
     * @return list<array{formNormalised: string, personCount: int}>
     */
    public function findSharedNormalisedForms(): array
    {
        /** @var list<array{formNormalised: string, personCount: int}> $rows */
        $rows = $this->createQueryBuilder('n')
            ->select('n.formNormalised AS formNormalised', 'COUNT(DISTINCT n.person) AS personCount')
            ->groupBy('n.formNormalised')
            ->having('COUNT(DISTINCT n.person) > 1')
            ->orderBy('n.formNormalised', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
