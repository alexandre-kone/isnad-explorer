<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Enum\NameScript;
use App\Entity\Person;
use App\Repository\PersonRepository;

/**
 * Recherche d'un transmetteur sur **toutes** ses formes de nom.
 *
 * C'est le seul mécanisme qui empêche les fiches de diverger : à plusieurs
 * curateurs, celui qui ne retrouve pas « al-Nuʿmān ibn Thābit » en tapant
 * « Abū Ḥanīfa » crée une seconde fiche pour le même homme.
 */
final class PersonSearch
{
    public function __construct(
        private readonly PersonRepository $people,
        private readonly PersonNameNormaliser $normaliser,
    ) {
    }

    /**
     * @return list<array{person: Person, matched: string, context: string}>
     */
    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ('' === $term) {
            return [];
        }

        $normalised = $this->normaliser->normalise($term, $this->scriptOf($term));

        $results = [];
        foreach ($this->people->searchByNormalisedForm($normalised, $limit) as $row) {
            $person = $row['person'];
            $results[] = [
                'person' => $person,
                'matched' => $row['matched'],
                'context' => $this->context($person),
            ];
        }

        return $results;
    }

    /**
     * Le contexte discriminant n'est pas décoratif : c'est lui qui permet de
     * distinguer Sufyân ibn ʿUyayna de Sufyân al-Thawrî dans une liste.
     */
    private function context(Person $person): string
    {
        $parts = array_filter([
            $person->getPeriod()->getLabelFr(),
            $person->getDeathLabel(),
            $person->getRegion(),
        ]);

        return implode(' · ', $parts);
    }

    /**
     * L'écriture se déduit du terme saisi : un curateur ne choisit pas dans
     * quelle langue il cherche.
     */
    private function scriptOf(string $term): NameScript
    {
        return preg_match('/\p{Arabic}/u', $term) ? NameScript::Arabic : NameScript::Latin;
    }
}
