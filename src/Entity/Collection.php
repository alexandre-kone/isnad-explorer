<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\CollectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
// Le nom court est pris par l'entité : ici, une collection Doctrine s'écrit
// DoctrineCollection.
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un recueil de hadiths (Ṣaḥīḥ al-Bukhārī, Sunan Abī Dāwūd…).
 *
 * `titleKey` joue pour les recueils le rôle que `formNormalised` joue pour les
 * noms : sans elle, « Muslim » et « Sahîh Muslim » fabriqueraient deux recueils
 * là où il n'y en a qu'un.
 */
#[ORM\Entity(repositoryClass: CollectionRepository::class)]
#[ORM\Table(name: 'collection')]
#[ORM\UniqueConstraint(name: 'uniq_collection_title_key', columns: ['title_key'])]
class Collection implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $title;

    /** Calculée par {@see \App\Domain\ReferenceParser} — jamais saisie. */
    #[ORM\Column(length: 255)]
    private string $titleKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleAr = null;

    /** L'auteur d'un recueil est un transmetteur comme un autre : il a sa fiche. */
    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Person $compiler = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var DoctrineCollection<int, Edition> */
    #[ORM\OneToMany(targetEntity: Edition::class, mappedBy: 'collection', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['label' => 'ASC'])]
    private DoctrineCollection $editions;

    public function __construct(string $slug, string $title, string $titleKey)
    {
        $this->slug = $slug;
        $this->title = $title;
        $this->titleKey = $titleKey;
        $this->editions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getTitleAr(): ?string
    {
        return $this->titleAr;
    }

    public function setTitleAr(?string $titleAr): self
    {
        $this->titleAr = $titleAr;

        return $this;
    }

    public function getCompiler(): ?Person
    {
        return $this->compiler;
    }

    public function setCompiler(?Person $compiler): self
    {
        $this->compiler = $compiler;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return DoctrineCollection<int, Edition> */
    public function getEditions(): DoctrineCollection
    {
        return $this->editions;
    }

    public function addEdition(Edition $edition): self
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
        }

        return $this;
    }

    /** L'édition dont la numérotation fait foi, s'il y en a une de désignée. */
    public function getReferenceEdition(): ?Edition
    {
        foreach ($this->editions as $edition) {
            if ($edition->isReference()) {
                return $edition;
            }
        }

        return null;
    }
}
