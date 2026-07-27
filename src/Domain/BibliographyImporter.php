<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Collection;
use App\Entity\Riwaya;
use App\Entity\RiwayaReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transforme la référence composite d'une riwāya en références structurées.
 *
 * Les recueils sont dédupliqués sur toute la durée d'un import : « Sahîh
 * al-Bukhârî » cité par quatre riwāyāt ne donne qu'une ligne `collection`.
 */
final class BibliographyImporter
{
    /** @var array<string, Collection> */
    private array $collections = [];

    public function __construct(
        private readonly ReferenceParser $parser = new ReferenceParser(),
    ) {
    }

    /**
     * Réexécutable : une citation déjà enregistrée est ignorée, pas dupliquée.
     * Les entités créées sont persistées mais pas flushées, l'appelant décide
     * du moment de l'écriture.
     *
     * @return list<RiwayaReference>
     */
    public function import(EntityManagerInterface $em, Riwaya $riwaya): array
    {
        $created = [];

        foreach ($this->parser->parse($riwaya->getReference()) as $position => $citation) {
            $collection = $this->collection($em, $citation);

            if ($this->alreadyReferenced($riwaya, $collection, $citation->number)) {
                continue;
            }

            $reference = new RiwayaReference($riwaya, $collection, $citation->number);
            $reference->setPosition($position)
                ->setPrimary(0 === $position && $riwaya->getReferences()->isEmpty());

            $riwaya->addReference($reference);
            $em->persist($reference);
            $created[] = $reference;
        }

        return $created;
    }

    private function alreadyReferenced(Riwaya $riwaya, Collection $collection, ?string $number): bool
    {
        foreach ($riwaya->getReferences() as $reference) {
            if ($reference->getCollection() === $collection && $reference->getNumber() === $number) {
                return true;
            }
        }

        return false;
    }

    private function collection(EntityManagerInterface $em, Citation $citation): Collection
    {
        $collection = $this->resolve($em, $citation);

        // Le corpus cite tantôt « Muslim », tantôt « Sahîh Muslim » : la forme
        // la plus complète fait un meilleur titre par défaut.
        if (mb_strlen($citation->title) > mb_strlen($collection->getTitle())) {
            $collection->setTitle($citation->title);
        }

        return $this->collections[$citation->titleKey] = $collection;
    }

    /**
     * Le cache est revalidé à chaque appel : après un `clear()` de
     * l'EntityManager, une entité mémorisée serait détachée et sa réutilisation
     * lèverait une erreur au flush.
     */
    private function resolve(EntityManagerInterface $em, Citation $citation): Collection
    {
        $cached = $this->collections[$citation->titleKey] ?? null;
        if (null !== $cached && $em->contains($cached)) {
            return $cached;
        }

        $collection = $em->getRepository(Collection::class)->findOneBy(['titleKey' => $citation->titleKey]);
        if (null !== $collection) {
            return $collection;
        }

        $collection = new Collection(
            $this->slug($em, $citation->titleKey),
            $citation->title,
            $citation->titleKey,
        );
        $em->persist($collection);

        return $collection;
    }

    /**
     * Deux clés distinctes peuvent donner le même slug, unique en base : on
     * suffixe plutôt que de laisser l'import échouer au flush.
     */
    private function slug(EntityManagerInterface $em, string $titleKey): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $titleKey), '-');
        $base = '' === $base ? 'recueil' : mb_substr($base, 0, 60);

        $slug = $base;
        $suffix = 1;
        while ($this->taken($em, $slug)) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    /**
     * Les recueils créés pendant l'import ne sont pas encore en base : le cache
     * complète la requête, en ignorant ses entrées devenues détachées.
     */
    private function taken(EntityManagerInterface $em, string $slug): bool
    {
        foreach ($this->collections as $collection) {
            if ($collection->getSlug() === $slug && $em->contains($collection)) {
                return true;
            }
        }

        return null !== $em->getRepository(Collection::class)->findOneBy(['slug' => $slug]);
    }
}
