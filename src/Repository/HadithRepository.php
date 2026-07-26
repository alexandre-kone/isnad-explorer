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
     * Recherche les hadiths dont le texte français contient le terme
     * (insensible à la casse), graphe de transmission préchargé.
     *
     * La limite s'applique bien aux hadiths (et non aux lignes jointes du
     * graphe) : {@see Paginator} pagine sur les entités racines tout en
     * hydratant l'intégralité des arêtes.
     *
     * @return list<Hadith>
     */
    public function searchByMatn(string $term, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('h')
            ->leftJoin('h.transmissions', 't')->addSelect('t')
            ->leftJoin('t.from', 'pf')->addSelect('pf')
            ->leftJoin('t.to', 'pt')->addSelect('pt')
            ->where("LOWER(h.textFr) LIKE :term ESCAPE '\\'")
            ->setParameter('term', '%'.self::escapeLike(mb_strtolower($term)).'%')
            ->orderBy('h.reference', 'ASC')
            ->setMaxResults($limit)
            ->getQuery();

        return array_values(iterator_to_array(new Paginator($query, fetchJoinCollection: true)));
    }

    /**
     * Tous les hadiths avec leur graphe complet, en une requête par relation
     * plutôt qu'un N+1 sur les 4 hadiths du corpus.
     *
     * @return list<Hadith>
     */
    public function findAllWithGraph(): array
    {
        $qb = $this->createQueryBuilder('h')
            ->leftJoin('h.participants', 'hp')->addSelect('hp')
            ->leftJoin('hp.person', 'p')->addSelect('p')
            ->leftJoin('p.period', 'per')->addSelect('per')
            ->leftJoin('h.pivot', 'pv')->addSelect('pv')
            ->orderBy('h.id', 'ASC');

        /** @var list<Hadith> $hadiths */
        $hadiths = $qb->getQuery()->getResult();

        // Les arêtes sont chargées à part : jointes à la requête ci-dessus,
        // elles multiplieraient les lignes par le nombre de participants.
        $this->createQueryBuilder('h2')
            ->leftJoin('h2.transmissions', 't')->addSelect('t')
            ->leftJoin('t.from', 'pf')->addSelect('pf')
            ->leftJoin('t.to', 'pt')->addSelect('pt')
            ->getQuery()
            ->getResult();

        return $hadiths;
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
