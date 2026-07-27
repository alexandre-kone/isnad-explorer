<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HadithCluster;
use App\Entity\Riwaya;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HadithCluster>
 */
final class HadithClusterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HadithCluster::class);
    }

    /**
     * Tous les enseignements avec le graphe de leurs riwāyāt, en une requête
     * par relation plutôt qu'un N+1 sur les 4 clusters du corpus.
     *
     * @return list<HadithCluster>
     */
    public function findAllWithGraph(): array
    {
        /** @var list<HadithCluster> $clusters */
        $clusters = $this->createQueryBuilder('c')
            ->leftJoin('c.riwayat', 'r')->addSelect('r')
            ->leftJoin('r.participants', 'rp')->addSelect('rp')
            ->leftJoin('rp.person', 'p')->addSelect('p')
            ->leftJoin('p.names', 'pn')->addSelect('pn')
            ->leftJoin('p.period', 'per')->addSelect('per')
            ->leftJoin('r.pivot', 'pv')->addSelect('pv')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Les arêtes sont chargées à part : jointes à la requête ci-dessus,
        // elles multiplieraient les lignes par le nombre de participants.
        $this->getEntityManager()->createQueryBuilder()
            ->select('r2', 't', 'pf', 'pfn', 'pt', 'ptn')
            ->from(Riwaya::class, 'r2')
            ->leftJoin('r2.transmissions', 't')
            ->leftJoin('t.from', 'pf')
            ->leftJoin('pf.names', 'pfn')
            ->leftJoin('t.to', 'pt')
            ->leftJoin('pt.names', 'ptn')
            ->getQuery()
            ->getResult();

        return $clusters;
    }
}
