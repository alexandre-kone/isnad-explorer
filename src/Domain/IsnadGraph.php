<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Enum\NameScript;
use App\Entity\HadithCluster;
use App\Entity\Person;
use App\Entity\Riwaya;
use App\Repository\HadithClusterRepository;
use App\Repository\PeriodRepository;

/**
 * Construit la charge utile du graphe consommée par la vue Réseau.
 *
 * La forme reproduit exactement celle du module du wireframe
 * (window.PERIODS / window.HADITHS) : l'îlot Stimulus de la phase 3 pourra
 * consommer ce JSON sans transformation.
 *
 * Les relations amont/aval (« up » / « down ») ne sont pas stockées : elles se
 * déduisent des arêtes, comme le prévoyait le schéma de référence.
 */
final class IsnadGraph
{
    /** Au-delà, la liste des voisins est tronquée pour rester lisible. */
    private const int MAX_NEIGHBOURS = 6;

    public function __construct(
        private readonly PeriodRepository $periods,
        private readonly HadithClusterRepository $clusters,
    ) {
    }

    /**
     * @return array{periods: array<string, array<string, mixed>>, hadiths: array<string, array<string, mixed>>}
     */
    public function payload(): array
    {
        $periods = [];
        foreach ($this->periods->findOrdered() as $period) {
            $periods[$period->getId()] = [
                'fr' => $period->getLabelFr(),
                'ar' => $period->getLabelAr(),
                'color' => $period->getColor(),
                'order' => $period->getSortOrder(),
            ];
        }

        $hadiths = [];
        foreach ($this->clusters->findAllWithGraph() as $cluster) {
            $riwaya = $cluster->getPrimaryRiwaya();
            if (null === $riwaya) {
                continue;
            }

            $hadiths[$cluster->getSlug()] = $this->hadith($cluster, $riwaya);
        }

        return ['periods' => $periods, 'hadiths' => $hadiths];
    }

    /**
     * @return array<string, mixed>
     */
    private function hadith(HadithCluster $cluster, Riwaya $riwaya): array
    {
        [$up, $down] = $this->neighbours($riwaya);

        $rawis = [];
        foreach ($riwaya->getParticipants() as $participant) {
            $person = $participant->getPerson();
            $slug = $person->getSlug();

            $rawi = [
                'lvl' => $participant->getLevel(),
                'name' => $person->getDisplayName(NameScript::Latin),
                'ar' => $person->getDisplayName(NameScript::Arabic),
                'gen' => $person->getPeriod()->getId(),
                'meta' => $person->getMeta(),
                'region' => $participant->getRegion(),
            ];

            foreach (['role' => $person->getRole(), 'bio' => $participant->getBio(), 'work' => $participant->getWork()] as $key => $value) {
                if (null !== $value && '' !== $value) {
                    $rawi[$key] = $value;
                }
            }

            if (isset($up[$slug])) {
                $rawi['up'] = $this->join($up[$slug]);
            }
            if (isset($down[$slug])) {
                $rawi['down'] = $this->join($down[$slug]);
            }

            if ($riwaya->getPivot()?->getSlug() === $slug) {
                $rawi['pivot'] = true;
                $rawi['chains'] = $participant->getChains();
            }

            $rawis[$slug] = $rawi;
        }

        $links = [];
        foreach ($riwaya->getTransmissions() as $transmission) {
            $link = [$transmission->getFrom()->getSlug(), $transmission->getTo()->getSlug()];
            if ($transmission->isSpine()) {
                $link[] = true;
            }
            $links[] = $link;
        }

        return [
            'key' => $cluster->getSlug(),
            'label' => $cluster->getLabel(),
            'fr' => $riwaya->getTextFr(),
            'ar' => $riwaya->getTextAr(),
            'ref' => $riwaya->getReference(),
            'grade' => $riwaya->getGrade(),
            'theme' => $cluster->getTheme(),
            'intro' => $cluster->getIntro(),
            'turuq' => $cluster->getTuruq(),
            'pivot' => $riwaya->getPivot()?->getSlug(),
            'ready' => $cluster->isReady(),
            'rawis' => $rawis,
            'links' => $links,
        ];
    }

    /**
     * Maîtres (amont) et élèves (aval) de chaque transmetteur, déduits des arêtes.
     *
     * @return array{array<string, list<string>>, array<string, list<string>>}
     */
    private function neighbours(Riwaya $riwaya): array
    {
        $up = [];
        $down = [];

        foreach ($riwaya->getTransmissions() as $transmission) {
            $from = $transmission->getFrom();
            $to = $transmission->getTo();

            $down[$from->getSlug()][] = $this->shortName($to);
            $up[$to->getSlug()][] = $this->shortName($from);
        }

        return [$up, $down];
    }

    /**
     * @param list<string> $names
     */
    private function join(array $names): string
    {
        $unique = array_values(array_unique($names));
        sort($unique);

        $shown = \array_slice($unique, 0, self::MAX_NEIGHBOURS);

        return implode(', ', $shown).(\count($unique) > self::MAX_NEIGHBOURS ? '…' : '');
    }

    /**
     * Le ﷺ alourdit les listes de voisins : on ne le garde que sur le nœud.
     */
    private function shortName(Person $person): string
    {
        return trim(str_replace('ﷺ', '', (string) $person->getDisplayName(NameScript::Latin)));
    }
}
