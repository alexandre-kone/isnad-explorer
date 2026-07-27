<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\RiwayaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * L'unité réelle de la science du hadith : un isnad, un matn, une citation.
 *
 * Un même enseignement circule par plusieurs voies (turuq), et chaque voie a sa
 * propre variante de texte. C'est ici que vivent le texte et la chaîne ; le
 * {@see HadithCluster} au-dessus ne porte que le jugement d'équivalence.
 */
#[ORM\Entity(repositoryClass: RiwayaRepository::class)]
#[ORM\Table(name: 'riwaya')]
class Riwaya implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HadithCluster::class, inversedBy: 'riwayat')]
    #[ORM\JoinColumn(nullable: false)]
    private HadithCluster $cluster;

    #[ORM\Column(length: 64, unique: true)]
    private string $slug;

    /** Texte français (matn traduit). */
    #[ORM\Column(type: 'text')]
    private string $textFr;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textAr = null;

    /**
     * Citation verbatim, ex. « Sahîh al-Bukhârî, n°1 ». Sa version structurée
     * vit dans {@see RiwayaReference}, et c'est elle qu'il faut interroger.
     */
    #[ORM\Column(length: 255)]
    private string $reference;

    /**
     * Grade de fiabilité, ex. « Sahîh ». Il porte sur cette voie : deux voies
     * du même enseignement ne se valent pas nécessairement.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $grade = null;

    /** Le مدار : narrateur sur lequel toutes les voies convergent. */
    #[ORM\ManyToOne(targetEntity: Person::class)]
    private ?Person $pivot = null;

    /** @var Collection<int, RiwayaParticipant> */
    #[ORM\OneToMany(targetEntity: RiwayaParticipant::class, mappedBy: 'riwaya', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['level' => 'ASC'])]
    private Collection $participants;

    /** @var Collection<int, Transmission> */
    #[ORM\OneToMany(targetEntity: Transmission::class, mappedBy: 'riwaya', cascade: ['persist'], orphanRemoval: true)]
    private Collection $transmissions;

    /** @var Collection<int, RiwayaReference> */
    #[ORM\OneToMany(targetEntity: RiwayaReference::class, mappedBy: 'riwaya', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $references;

    public function __construct(HadithCluster $cluster, string $slug, string $textFr, string $reference)
    {
        $this->cluster = $cluster;
        $this->slug = $slug;
        $this->textFr = $textFr;
        $this->reference = $reference;
        $this->participants = new ArrayCollection();
        $this->transmissions = new ArrayCollection();
        $this->references = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCluster(): HadithCluster
    {
        return $this->cluster;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTextFr(): string
    {
        return $this->textFr;
    }

    public function getTextAr(): ?string
    {
        return $this->textAr;
    }

    public function setTextAr(?string $textAr): self
    {
        $this->textAr = $textAr;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): self
    {
        $this->grade = $grade;

        return $this;
    }

    public function getPivot(): ?Person
    {
        return $this->pivot;
    }

    public function setPivot(?Person $pivot): self
    {
        $this->pivot = $pivot;

        return $this;
    }

    /** @return Collection<int, RiwayaParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection
    {
        return $this->transmissions;
    }

    /** @return Collection<int, RiwayaReference> */
    public function getReferences(): Collection
    {
        return $this->references;
    }

    public function addParticipant(Person $person, int $level): RiwayaParticipant
    {
        $participant = new RiwayaParticipant($this, $person, $level);
        $this->participants->add($participant);

        return $participant;
    }

    public function addTransmission(Person $from, Person $to, bool $spine = false): self
    {
        $this->transmissions->add(new Transmission($this, $from, $to, $spine));

        return $this;
    }

    /**
     * L'unicité `(riwāya, recueil, numéro)` est tenue ici et pas seulement par
     * l'index : PostgreSQL considère deux `NULL` comme distincts, or le numéro
     * nul est un cas prévu — « recueil cité sans numéro ».
     */
    public function addReference(RiwayaReference $reference): self
    {
        foreach ($this->references as $existing) {
            if ($existing === $reference) {
                return $this;
            }

            if ($existing->getCollection() === $reference->getCollection()
                && $existing->getNumber() === $reference->getNumber()) {
                throw new \InvalidArgumentException(\sprintf(
                    '« %s » est déjà référencée dans %s.',
                    $this->slug,
                    $reference->getCollection()->getTitle(),
                ));
            }
        }

        $this->references->add($reference);

        return $this;
    }

    /**
     * Chaîne unique (gharîb) : le segment initial où l'isnad ne se divise pas,
     * du Prophète ﷺ jusqu'au pivot. Reconstituée en suivant les arêtes marquées
     * comme épine, depuis le nœud d'épine qui n'est la cible d'aucune autre.
     *
     * @return list<Person>
     */
    public function getSpine(): array
    {
        /** @var array<string, Transmission> $next */
        $next = [];
        $targets = [];
        foreach ($this->transmissions as $transmission) {
            if (!$transmission->isSpine()) {
                continue;
            }
            $next[$transmission->getFrom()->getSlug()] = $transmission;
            $targets[$transmission->getTo()->getSlug()] = true;
        }

        $current = null;
        foreach ($next as $slug => $transmission) {
            if (!isset($targets[$slug])) {
                $current = $transmission->getFrom();
                break;
            }
        }

        if (null === $current) {
            return [];
        }

        $chain = [$current];
        $seen = [$current->getSlug() => true];
        while (isset($next[$current->getSlug()])) {
            $current = $next[$current->getSlug()]->getTo();
            if (isset($seen[$current->getSlug()])) {
                break; // garde-fou : le jeu de données ne doit pas boucler
            }
            $seen[$current->getSlug()] = true;
            $chain[] = $current;
        }

        return $chain;
    }
}
