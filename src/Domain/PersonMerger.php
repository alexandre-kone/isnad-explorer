<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\HadithParticipant;
use App\Entity\Person;
use App\Entity\PersonMergeLog;
use App\Entity\Transmission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fusionne deux fiches désignant le même transmetteur.
 *
 * Le piège n'est pas le repointage lui-même mais les **collisions** qu'il crée.
 * Si `A → C` et `B → C` existent et qu'on fusionne B dans A, le repointage
 * produit deux fois `A → C`, ce que la contrainte d'unicité de
 * {@see Transmission} refuse. Il faut donc fusionner les arêtes avant de
 * repointer. Même problème sur les participations à un hadith.
 */
final class PersonMerger
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Ce que la fusion touchera, calculé sans rien modifier : l'écran de
     * confirmation l'affiche pour que le curateur mesure l'ampleur.
     *
     * @return array{names: int, participations: int, transmissions: int, pivots: int, collisions: int}
     */
    public function preview(Person $absorbed, Person $kept): array
    {
        $keptEdges = $this->edgeKeys($kept);
        $collisions = 0;

        foreach ($this->transmissionsOf($absorbed) as $transmission) {
            if (isset($keptEdges[$this->edgeKeyAfterMerge($transmission, $absorbed, $kept)])) {
                ++$collisions;
            }
        }

        return [
            'names' => $absorbed->getNames()->count(),
            'participations' => \count($this->participationsOf($absorbed)),
            'transmissions' => \count($this->transmissionsOf($absorbed)),
            'pivots' => \count($this->em->getRepository(\App\Entity\Hadith::class)->findBy(['pivot' => $absorbed])),
            'collisions' => $collisions,
        ];
    }

    /**
     * @throws \InvalidArgumentException si les deux fiches sont la même
     */
    public function merge(Person $absorbed, Person $kept, ?User $author): PersonMergeLog
    {
        if ($absorbed->getId() === $kept->getId()) {
            throw new \InvalidArgumentException('Impossible de fusionner une fiche avec elle-même.');
        }

        $impact = $this->preview($absorbed, $kept);
        $label = $absorbed->getDisplayName() ?? $absorbed->getSlug();

        $transferred = [];

        $this->em->wrapInTransaction(function () use ($absorbed, $kept, &$transferred): void {
            $transferred = $this->transferNames($absorbed, $kept);
            $this->mergeParticipations($absorbed, $kept);
            $this->mergeTransmissions($absorbed, $kept);
            $this->repointPivots($absorbed, $kept);

            $this->em->remove($absorbed);
            $this->em->flush();
        });

        $log = new PersonMergeLog($absorbed->getSlug(), $label, $kept, $transferred, $impact, $author);
        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    /**
     * @return list<array{form: string, script: string, kind: string}>
     */
    private function transferNames(Person $absorbed, Person $kept): array
    {
        $existing = [];
        foreach ($kept->getNames() as $name) {
            $existing[$name->getScript()->value.'|'.$name->getForm()] = true;
        }

        $transferred = [];
        foreach ($absorbed->getNames()->toArray() as $name) {
            $transferred[] = [
                'form' => $name->getForm(),
                'script' => $name->getScript()->value,
                'kind' => $name->getKind()->value,
            ];

            if (isset($existing[$name->getScript()->value.'|'.$name->getForm()])) {
                // Forme déjà présente sur la fiche conservée : inutile de la
                // dupliquer, la contrainte d'unicité la refuserait.
                $this->em->remove($name);

                continue;
            }

            $absorbed->removeName($name);
            // Une forme reprise ne peut pas rester « forme d'affichage » : la
            // fiche conservée a déjà la sienne.
            $name->setDisplay(false)->setPerson($kept);
            $kept->addName($name);
        }

        return $transferred;
    }

    private function mergeParticipations(Person $absorbed, Person $kept): void
    {
        $keptByHadith = [];
        foreach ($this->participationsOf($kept) as $participation) {
            $keptByHadith[(int) $participation->getHadith()->getId()] = $participation;
        }

        foreach ($this->participationsOf($absorbed) as $participation) {
            $hadithId = (int) $participation->getHadith()->getId();
            $rival = $keptByHadith[$hadithId] ?? null;

            if (null === $rival) {
                $participation->setPerson($kept);
                $keptByHadith[$hadithId] = $participation;

                continue;
            }

            // Les deux fiches participaient au même hadith : on garde le niveau
            // le plus proche du Prophète ﷺ, et on jette le doublon.
            if ($participation->getLevel() < $rival->getLevel()) {
                $rival->setLevel($participation->getLevel());
            }
            $this->em->remove($participation);
        }
    }

    private function mergeTransmissions(Person $absorbed, Person $kept): void
    {
        $keptEdges = $this->edgeKeys($kept);

        foreach ($this->transmissionsOf($absorbed) as $transmission) {
            $key = $this->edgeKeyAfterMerge($transmission, $absorbed, $kept);
            $rival = $keptEdges[$key] ?? null;

            if (null !== $rival) {
                // Collision : une seule arête survit, et elle hérite du
                // caractère gharîb si l'une des deux le portait.
                if ($transmission->isSpine()) {
                    $rival->setSpine(true);
                }
                $this->em->remove($transmission);

                continue;
            }

            if ($transmission->getFrom()->getId() === $absorbed->getId()) {
                $transmission->setFrom($kept);
            }
            if ($transmission->getTo()->getId() === $absorbed->getId()) {
                $transmission->setTo($kept);
            }

            $keptEdges[$key] = $transmission;
        }
    }

    private function repointPivots(Person $absorbed, Person $kept): void
    {
        foreach ($this->em->getRepository(\App\Entity\Hadith::class)->findBy(['pivot' => $absorbed]) as $hadith) {
            $hadith->setPivot($kept);
        }
    }

    /**
     * Arêtes de la fiche conservée, indexées par leur identité logique.
     *
     * @return array<string, Transmission>
     */
    private function edgeKeys(Person $person): array
    {
        $keys = [];
        foreach ($this->transmissionsOf($person) as $transmission) {
            $keys[\sprintf(
                '%d|%d|%d',
                $transmission->getHadith()->getId(),
                $transmission->getFrom()->getId(),
                $transmission->getTo()->getId(),
            )] = $transmission;
        }

        return $keys;
    }

    /**
     * Identité qu'aurait l'arête une fois l'absorbée remplacée par la conservée.
     */
    private function edgeKeyAfterMerge(Transmission $transmission, Person $absorbed, Person $kept): string
    {
        $from = $transmission->getFrom()->getId() === $absorbed->getId() ? $kept : $transmission->getFrom();
        $to = $transmission->getTo()->getId() === $absorbed->getId() ? $kept : $transmission->getTo();

        return \sprintf('%d|%d|%d', $transmission->getHadith()->getId(), $from->getId(), $to->getId());
    }

    /**
     * @return list<HadithParticipant>
     */
    private function participationsOf(Person $person): array
    {
        return $this->em->getRepository(HadithParticipant::class)->findBy(['person' => $person]);
    }

    /**
     * @return list<Transmission>
     */
    private function transmissionsOf(Person $person): array
    {
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(Transmission::class, 't')
            ->where('t.from = :person OR t.to = :person')
            ->setParameter('person', $person)
            ->getQuery()
            ->getResult();
    }
}
