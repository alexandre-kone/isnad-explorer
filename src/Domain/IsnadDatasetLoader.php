<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Enum\NameKind;
use App\Entity\Enum\NameScript;
use App\Entity\HadithCluster;
use App\Entity\Period;
use App\Entity\Person;
use App\Entity\PersonName;
use App\Entity\Riwaya;
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

    public function __construct(
        private readonly string $datasetPath,
        private readonly PersonNameNormaliser $normaliser = new PersonNameNormaliser(),
        private readonly BibliographyImporter $bibliography = new BibliographyImporter(),
    ) {
    }

    /**
     * @return array{periods: int, people: int, hadiths: int, riwayat: int, transmissions: int, references: int}
     */
    public function load(EntityManagerInterface $em): array
    {
        $data = $this->readDataset();

        $this->periods = [];
        $this->people = [];
        $transmissions = 0;
        $references = 0;

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

            $cluster = new HadithCluster($slug, $raw['label']);
            $cluster->setTheme($raw['theme'] ?? null)
                ->setIntro($raw['intro'] ?? null)
                ->setTuruq($raw['turuq'] ?? null)
                ->setReady((bool) ($raw['ready'] ?? true));

            // Le jeu de données décrit un texte et un graphe fusionnés par
            // enseignement, sans distinguer les voies : il produit donc une
            // riwāya unique. Le découpage en voies réelles est un geste de
            // curation, pas une déduction d'import.
            $riwaya = new Riwaya($cluster, $slug, $raw['fr'], $raw['ref']);
            $riwaya->setTextAr($raw['ar'] ?? null)
                ->setGrade($raw['grade'] ?? null);
            $cluster->addRiwaya($riwaya);

            foreach ($raw['rawis'] as $personSlug => $rawi) {
                $person = $this->person($em, $personSlug, $rawi);
                $participant = $riwaya->addParticipant($person, (int) $rawi['lvl']);

                // bio / work / region varient d'une occurrence à l'autre pour
                // une même personne : ils sont portés par la participation.
                $participant->setBio($rawi['bio'] ?? null)
                    ->setWork($rawi['work'] ?? null)
                    ->setRegion($rawi['region'] ?? null);

                if ($rawi['pivot'] ?? false) {
                    $riwaya->setPivot($person);
                    $participant->setChains($rawi['chains'] ?? null);
                }
            }

            foreach ($raw['links'] as $link) {
                [$from, $to] = $link;
                $riwaya->addTransmission(
                    $this->people[$from],
                    $this->people[$to],
                    (bool) ($link[2] ?? false),
                );
                ++$transmissions;
            }

            $em->persist($cluster);
            $em->persist($riwaya);
            ++$hadiths;

            $references += \count($this->bibliography->import($em, $riwaya));
        }

        $em->flush();

        return [
            'periods' => \count($this->periods),
            'people' => \count($this->people),
            'hadiths' => $hadiths,
            'riwayat' => $hadiths,
            'transmissions' => $transmissions,
            'references' => $references,
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

        $person = new Person($slug, $this->periods[$rawi['gen']]);
        $person->setMeta($rawi['meta'] ?? null)
            ->setRegion($rawi['region'] ?? null)
            ->setRole($rawi['role'] ?? null)
            ->setBio($rawi['bio'] ?? null)
            ->setWork($rawi['work'] ?? null);

        // Le jeu de données ne donne qu'une forme par écriture : elles servent
        // donc toutes deux d'affichage. Les formes alternatives (kunya, shuhra)
        // seront saisies par les curateurs.
        $this->addName($em, $person, $rawi['name'], NameScript::Latin);
        if (isset($rawi['ar']) && '' !== $rawi['ar']) {
            $this->addName($em, $person, $rawi['ar'], NameScript::Arabic);
        }

        $em->persist($person);

        return $this->people[$slug] = $person;
    }

    private function addName(EntityManagerInterface $em, Person $person, string $form, NameScript $script): void
    {
        $name = new PersonName(
            $person,
            $form,
            $this->normaliser->normalise($form, $script),
            $script,
            NameKind::Complete,
        );
        $name->setDisplay(true)->setSource('wireframe');

        $person->addName($name);
        $em->persist($name);
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
