<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Collection;
use App\Entity\Hadith;
use App\Entity\HadithReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transforme la référence composite d'un hadith en références structurées.
 *
 * Les recueils sont dédupliqués sur toute la durée d'un import : « Sahîh
 * al-Bukhârî » cité par quatre hadiths ne donne qu'une ligne `collection`.
 */
final class BibliographyImporter
{
    /** @var array<string, Collection> */
    private array $collections = [];

    /** @var array<string, true> Slugs déjà attribués, y compris avant flush. */
    private array $slugs = [];

    public function __construct(
        private readonly ReferenceParser $parser = new ReferenceParser(),
    ) {
    }

    /**
     * Les entités créées sont persistées mais pas flushées : l'appelant décide
     * du moment de l'écriture.
     *
     * @return list<HadithReference>
     */
    public function import(EntityManagerInterface $em, Hadith $hadith): array
    {
        $references = [];

        foreach ($this->parser->parse($hadith->getReference()) as $position => $citation) {
            $reference = new HadithReference($hadith, $this->collection($em, $citation), $citation->number);
            $reference->setPosition($position)->setPrimary(0 === $position);

            $hadith->addReference($reference);
            $em->persist($reference);
            $references[] = $reference;
        }

        return $references;
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
        while (isset($this->slugs[$slug]) || null !== $em->getRepository(Collection::class)->findOneBy(['slug' => $slug])) {
            $slug = $base.'-'.++$suffix;
        }

        $this->slugs[$slug] = true;

        return $slug;
    }
}
