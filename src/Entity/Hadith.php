<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\HadithRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un hadith : son texte, ses métadonnées, et le graphe de sa transmission
 * ({@see HadithParticipant} pour les nœuds, {@see Transmission} pour les arêtes).
 *
 * L'isnad n'est plus une chaîne linéaire : au-delà du pivot (مدار) il s'évente
 * en plusieurs voies (turuq). Le segment où la chaîne reste unique est l'épine
 * gharîb, exposée par {@see getSpine()}.
 */
#[ORM\Entity(repositoryClass: HadithRepository::class)]
#[ORM\Table(name: 'hadith')]
class Hadith implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé stable, ex. « intention ». */
    #[ORM\Column(length: 64, unique: true)]
    private string $slug;

    /** Titre court, ex. « Hadith de l'intention ». */
    #[ORM\Column(length: 255)]
    private string $label;

    /** Texte français (matn traduit). */
    #[ORM\Column(type: 'text')]
    private string $textFr;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textAr = null;

    /**
     * Citation verbatim, ex. « Sahîh al-Bukhârî, n°1 ». Sa version structurée
     * vit dans {@see HadithReference}, et c'est elle qu'il faut interroger.
     */
    #[ORM\Column(length: 255)]
    private string $reference;

    /** Grade de fiabilité, ex. « Sahîh ». */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $grade = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $intro = null;

    /** Libellé du nombre de voies, ex. « 6 voies (turuq) ». */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $turuq = null;

    /** Le مدار : narrateur sur lequel toutes les voies convergent. */
    #[ORM\ManyToOne(targetEntity: Person::class)]
    private ?Person $pivot = null;

    /** Un hadith non prêt est listé mais pas explorable. */
    #[ORM\Column]
    private bool $ready = true;

    /** @var Collection<int, HadithParticipant> */
    #[ORM\OneToMany(targetEntity: HadithParticipant::class, mappedBy: 'hadith', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['level' => 'ASC'])]
    private Collection $participants;

    /** @var Collection<int, Transmission> */
    #[ORM\OneToMany(targetEntity: Transmission::class, mappedBy: 'hadith', cascade: ['persist'], orphanRemoval: true)]
    private Collection $transmissions;

    /** @var Collection<int, HadithReference> */
    #[ORM\OneToMany(targetEntity: HadithReference::class, mappedBy: 'hadith', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $references;

    public function __construct(string $slug, string $label, string $textFr, string $reference)
    {
        $this->slug = $slug;
        $this->label = $label;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
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

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): self
    {
        $this->intro = $intro;

        return $this;
    }

    public function getTuruq(): ?string
    {
        return $this->turuq;
    }

    public function setTuruq(?string $turuq): self
    {
        $this->turuq = $turuq;

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

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): self
    {
        $this->ready = $ready;

        return $this;
    }

    /** @return Collection<int, HadithParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection
    {
        return $this->transmissions;
    }

    /** @return Collection<int, HadithReference> */
    public function getReferences(): Collection
    {
        return $this->references;
    }

    /**
     * L'unicité `(hadith, recueil, numéro)` est tenue ici et pas seulement par
     * l'index : PostgreSQL considère deux `NULL` comme distincts, or le numéro
     * nul est un cas prévu — « recueil cité sans numéro ».
     */
    public function addReference(HadithReference $reference): self
    {
        foreach ($this->references as $existing) {
            if ($existing === $reference) {
                return $this;
            }

            if ($existing->getCollection() === $reference->getCollection()
                && $existing->getNumber() === $reference->getNumber()) {
                throw new \InvalidArgumentException(\sprintf(
                    '« %s » est déjà référencé dans %s.',
                    $this->slug,
                    $reference->getCollection()->getTitle(),
                ));
            }
        }

        $this->references->add($reference);

        return $this;
    }

    public function addParticipant(Person $person, int $level): HadithParticipant
    {
        $participant = new HadithParticipant($this, $person, $level);
        $this->participants->add($participant);

        return $participant;
    }

    public function addTransmission(Person $from, Person $to, bool $spine = false): self
    {
        $this->transmissions->add(new Transmission($this, $from, $to, $spine));

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
