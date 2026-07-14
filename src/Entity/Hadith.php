<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HadithRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un hadith : son texte (matn), sa référence de collection, et son isnad
 * (chaîne ordonnée de narrateurs, via {@see IsnadLink}).
 */
#[ORM\Entity(repositoryClass: HadithRepository::class)]
class Hadith
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Texte du hadith (matn). */
    #[ORM\Column(type: 'text')]
    private string $matn;

    /** Référence de collection, ex. « Sahih al-Bukhari 1 ». */
    #[ORM\Column(length: 255)]
    private string $reference;

    /**
     * Maillons de l'isnad, ordonnés du premier transmetteur (proche du
     * Prophète ﷺ) au compilateur.
     *
     * @var Collection<int, IsnadLink>
     */
    #[ORM\OneToMany(targetEntity: IsnadLink::class, mappedBy: 'hadith', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $isnadLinks;

    public function __construct(string $matn, string $reference)
    {
        $this->matn = $matn;
        $this->reference = $reference;
        $this->isnadLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatn(): string
    {
        return $this->matn;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return Collection<int, IsnadLink>
     */
    public function getIsnadLinks(): Collection
    {
        return $this->isnadLinks;
    }

    /**
     * Ajoute un narrateur en fin de chaîne (position auto-incrémentée).
     */
    public function addNarrator(Narrator $narrator): self
    {
        $this->isnadLinks->add(new IsnadLink($this, $narrator, $this->isnadLinks->count()));

        return $this;
    }

    /**
     * Narrateurs de l'isnad, dans l'ordre.
     *
     * @return list<Narrator>
     */
    public function getIsnad(): array
    {
        return $this->isnadLinks->map(static fn (IsnadLink $link): Narrator => $link->getNarrator())->getValues();
    }
}
