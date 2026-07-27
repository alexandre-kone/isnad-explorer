<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une édition imprimée d'un recueil.
 *
 * Elle existe pour une raison précise : **la numérotation des hadiths diffère
 * d'une édition à l'autre**, y compris pour Bukhârî. « n°13 » n'est donc pas un
 * identifiant tant qu'on ne dit pas dans quelle édition.
 */
#[ORM\Entity]
#[ORM\Table(name: 'edition')]
#[ORM\UniqueConstraint(name: 'uniq_edition_label', columns: ['collection_id', 'label'])]
class Edition implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false)]
    private Collection $collection;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearAh = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearAd = null;

    /** L'édition dont la numérotation fait foi pour ce recueil. */
    #[ORM\Column]
    private bool $reference = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(Collection $collection, string $label)
    {
        $this->collection = $collection;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getYearAh(): ?int
    {
        return $this->yearAh;
    }

    public function setYearAh(?int $yearAh): self
    {
        $this->yearAh = $yearAh;

        return $this;
    }

    public function getYearAd(): ?int
    {
        return $this->yearAd;
    }

    public function setYearAd(?int $yearAd): self
    {
        $this->yearAd = $yearAd;

        return $this;
    }

    public function isReference(): bool
    {
        return $this->reference;
    }

    public function setReference(bool $reference): self
    {
        $this->reference = $reference;

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

    public function getFullLabel(): string
    {
        $parts = array_filter([
            $this->label,
            null !== $this->yearAh ? $this->yearAh.' AH' : null,
            null !== $this->yearAd ? (string) $this->yearAd : null,
        ]);

        return implode(', ', $parts);
    }
}
