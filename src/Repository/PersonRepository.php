<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
final class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    public function findOneBySlug(string $slug): ?Person
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Cherche sur la forme normalisée de tous les noms, et renvoie la forme qui
     * a produit la correspondance — utile pour montrer au curateur *pourquoi*
     * une fiche remonte.
     *
     * `LIKE` plutôt que pg_trgm : dev et test tournent sur SQLite, un index
     * spécifique à PostgreSQL rendrait la recherche intestable.
     *
     * @return list<array{person: Person, matched: string}>
     */
    public function searchByNormalisedForm(string $normalised, int $limit = 10): array
    {
        /** @var list<array{0: Person, matched: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p', 'n.form AS matched')
            ->join('p.names', 'n')
            ->leftJoin('p.period', 'per')->addSelect('per')
            ->leftJoin('p.names', 'alln')->addSelect('alln')
            ->where("n.formNormalised LIKE :term ESCAPE '\\'")
            ->setParameter('term', '%'.self::escapeLike($normalised).'%')
            ->orderBy('n.formNormalised', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $results = [];
        $seen = [];
        foreach ($rows as $row) {
            $person = $row[0];
            $id = $person->getId();
            // Une personne peut correspondre par plusieurs formes : on ne la
            // propose qu'une fois, sur la première correspondance.
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $results[] = ['person' => $person, 'matched' => $row['matched']];
        }

        return $results;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
