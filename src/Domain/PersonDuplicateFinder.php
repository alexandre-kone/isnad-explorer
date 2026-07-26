<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Person;
use App\Repository\PersonNameRepository;
use App\Repository\PersonRepository;

/**
 * Repère les fiches qui désignent probablement le même transmetteur.
 *
 * À plusieurs curateurs qui ne se voient pas travailler, compter sur la
 * discipline de chacun ne suffit pas : deux fiches « Sufyân ibn ʿUyayna »
 * apparaîtront dans le même mois. Cet écran se passe en revue périodiquement.
 *
 * Un seul signal en phase 1, volontairement : deux fiches partageant une forme
 * de nom normalisée identique. Le rapprochement flou (dates voisines, noms
 * proches) viendra si le besoin se confirme — inutile de produire du bruit
 * avant d'avoir du volume.
 */
final class PersonDuplicateFinder
{
    public function __construct(
        private readonly PersonNameRepository $names,
        private readonly PersonRepository $people,
    ) {
    }

    /**
     * @return list<array{form: string, people: list<Person>}>
     */
    public function findExactFormCollisions(): array
    {
        $candidates = [];

        foreach ($this->names->findSharedNormalisedForms() as $row) {
            $matching = $this->people->createQueryBuilder('p')
                ->join('p.names', 'n')
                ->leftJoin('p.names', 'alln')->addSelect('alln')
                ->leftJoin('p.period', 'per')->addSelect('per')
                ->where('n.formNormalised = :form')
                ->setParameter('form', $row['formNormalised'])
                ->getQuery()
                ->getResult();

            if (\count($matching) < 2) {
                continue;
            }

            $candidates[] = ['form' => $row['formNormalised'], 'people' => array_values($matching)];
        }

        return $candidates;
    }
}
