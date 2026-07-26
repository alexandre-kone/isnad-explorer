<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Hadith;
use App\Entity\Period;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Charge le jeu de données d'isnads (data/isnad/wireframe.json) dans le modèle.
 *
 * Le fichier est la conversion JSON du module de données du wireframe
 * (window.PERIODS / window.HADITHS). Il est versionné pour que l'import soit
 * reproductible sans dépendre du dépôt du wireframe.
 *
 * Les personnes sont dédupliquées par clé sur l'ensemble des hadiths : un même
 * transmetteur partagé par plusieurs chaînes ne donne qu'une ligne `person`.
 */
final class IsnadDatasetLoader
{
    /** @var array<string, Person> */
    private array $people = [];

    /** @var array<string, Period> */
    private array $periods = [];

    public function __construct(private readonly string $datasetPath)
    {
    }

    /**
     * @return array{periods: int, people: int, hadiths: int, transmissions: int}
     */
    public function load(EntityManagerInterface $em): array
    {
        $data = $this->readDataset();

        $this->periods = [];
        $this->people = [];
        $transmissions = 0;

        foreach ($data['periods'] as $id => $period) {
            $entity = new Period($id, $period['fr'], $period['ar'], $period['color'], $period['order']);
            $em->persist($entity);
            $this->periods[$id] = $entity;
        }

        $hadiths = 0;
        foreach ($data['hadiths'] as $slug => $raw) {
            // Un hadith sans graphe (annoncé mais pas encore saisi) est ignoré :
            // il n'a rien à afficher dans la vue Réseau.
            if (!isset($raw['rawis'], $raw['links'])) {
                continue;
            }

            $hadith = new Hadith($slug, $raw['label'], $raw['fr'], $raw['ref']);
            $hadith->setTextAr($raw['ar'] ?? null)
                ->setGrade($raw['grade'] ?? null)
                ->setTheme($raw['theme'] ?? null)
                ->setIntro($raw['intro'] ?? null)
                ->setTuruq($raw['turuq'] ?? null)
                ->setReady((bool) ($raw['ready'] ?? true));

            foreach ($raw['rawis'] as $personSlug => $rawi) {
                $person = $this->person($em, $personSlug, $rawi);
                $participant = $hadith->addParticipant($person, (int) $rawi['lvl']);

                // bio / work / region varient d'un hadith à l'autre pour une
                // même personne : ils sont portés par la participation.
                $participant->setBio($rawi['bio'] ?? null)
                    ->setWork($rawi['work'] ?? null)
                    ->setRegion($rawi['region'] ?? null);

                if ($rawi['pivot'] ?? false) {
                    $hadith->setPivot($person);
                    $participant->setChains($rawi['chains'] ?? null);
                }
            }

            foreach ($raw['links'] as $link) {
                [$from, $to] = $link;
                $hadith->addTransmission(
                    $this->people[$from],
                    $this->people[$to],
                    (bool) ($link[2] ?? false),
                );
                ++$transmissions;
            }

            $em->persist($hadith);
            ++$hadiths;
        }

        $em->flush();

        return [
            'periods' => \count($this->periods),
            'people' => \count($this->people),
            'hadiths' => $hadiths,
            'transmissions' => $transmissions,
        ];
    }

    /**
     * Crée la personne au premier hadith qui la mentionne ; les suivants la
     * réutilisent telle quelle.
     *
     * @param array<string, mixed> $rawi
     */
    private function person(EntityManagerInterface $em, string $slug, array $rawi): Person
    {
        if (isset($this->people[$slug])) {
            return $this->people[$slug];
        }

        $person = new Person($slug, $rawi['name'], $this->periods[$rawi['gen']]);
        $person->setNameAr($rawi['ar'] ?? null)
            ->setMeta($rawi['meta'] ?? null)
            ->setRegion($rawi['region'] ?? null)
            ->setRole($rawi['role'] ?? null)
            ->setBio($rawi['bio'] ?? null)
            ->setWork($rawi['work'] ?? null);

        $em->persist($person);

        return $this->people[$slug] = $person;
    }

    /**
     * @return array{periods: array<string, array<string, mixed>>, hadiths: array<string, array<string, mixed>>}
     */
    private function readDataset(): array
    {
        if (!is_readable($this->datasetPath)) {
            throw new \RuntimeException(\sprintf('Jeu de données introuvable : %s', $this->datasetPath));
        }

        $decoded = json_decode((string) file_get_contents($this->datasetPath), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || !isset($decoded['periods'], $decoded['hadiths'])) {
            throw new \RuntimeException('Jeu de données invalide : clés « periods » et « hadiths » attendues.');
        }

        return $decoded;
    }
}
