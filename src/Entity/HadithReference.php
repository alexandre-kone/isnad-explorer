<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\HadithReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * « Ce hadith se trouve dans ce recueil, sous ce numéro. »
 *
 * C'est la ligne qui remplace la chaîne composite `Hadith.reference` : un hadith
 * en a autant que de recueils qui le rapportent, et chacune est joignable.
 *
 * L'édition est nullable, contrairement au modèle cible qui la voulait
 * obligatoire : les références héritées ne disent pas dans quelle édition le
 * numéro a été relevé, et fabriquer une édition pour satisfaire une contrainte
 * inventerait une information que personne n'a établie.
 */
#[ORM\Entity(repositoryClass: HadithReferenceRepository::class)]
#[ORM\Table(name: 'hadith_reference')]
#[ORM\UniqueConstraint(name: 'uniq_hadith_reference', columns: ['hadith_id', 'collection_id', 'number'])]
class HadithReference implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hadith::class, inversedBy: 'references')]
    #[ORM\JoinColumn(nullable: false)]
    private Hadith $hadith;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Collection $collection;

    #[ORM\ManyToOne(targetEntity: Edition::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Edition $edition = null;

    /** Le numéro tel que cité : « 13 », mais aussi « 45a » ou « 12/3 ». */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $number = null;

    /** `is_primary` en base : `primary` est un mot réservé de PostgreSQL. */
    #[ORM\Column(name: 'is_primary')]
    private bool $primary = false;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(Hadith $hadith, Collection $collection, ?string $number = null)
    {
        $this->hadith = $hadith;
        $this->collection = $collection;
        $this->number = $number;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHadith(): Hadith
    {
        return $this->hadith;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function getEdition(): ?Edition
    {
        return $this->edition;
    }

    /** Une édition d'un autre recueil rendrait la référence silencieusement fausse. */
    public function setEdition(?Edition $edition): self
    {
        if (null !== $edition && $edition->getCollection() !== $this->collection) {
            throw new \InvalidArgumentException(\sprintf(
                'L\'édition « %s » appartient à « %s », pas à « %s ».',
                $edition->getLabel(),
                $edition->getCollection()->getTitle(),
                $this->collection->getTitle(),
            ));
        }

        $this->edition = $edition;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function setPrimary(bool $primary): self
    {
        $this->primary = $primary;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): string
    {
        $title = $this->collection->getTitle();

        return null === $this->number ? $title : \sprintf('%s, n°%s', $title, $this->number);
    }
}
