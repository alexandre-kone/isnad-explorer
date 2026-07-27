<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\NameScript;
use App\Entity\Trait\Curated;
use App\Repository\PersonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un transmetteur (rāwī), nœud global du graphe.
 *
 * La personne est dédupliquée et partagée par tous les hadiths : c'est ce qui
 * rendra possible, plus tard, les corrélations entre chaînes. Ce qui dépend du
 * l'occurrence (niveau vertical, nombre de voies) vit dans {@see RiwayaParticipant}.
 *
 * Les libellés ne sont plus des colonnes : un homme porte plusieurs noms, tous
 * dans {@see PersonName}. La fiche ne garde que l'identité et les faits.
 */
#[ORM\Entity(repositoryClass: PersonRepository::class)]
#[ORM\Table(name: 'person')]
class Person implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé stable issue du jeu de données, ex. « yahya ». */
    #[ORM\Column(length: 64, unique: true)]
    private string $slug;

    /** @var Collection<int, PersonName> */
    #[ORM\OneToMany(targetEntity: PersonName::class, mappedBy: 'person', cascade: ['persist'], orphanRemoval: true)]
    private Collection $names;

    #[ORM\ManyToOne(targetEntity: Period::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Period $period;

    /** Légende courte affichée sous le nom, ex. « PIVOT · Qâdî · d. 143 AH ». */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    /** Pour un collecteur : le recueil dont il est l'auteur. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $work = null;

    /**
     * Dates hégiriennes en fourchettes : min = max signifie une date certaine,
     * les deux nuls une date inconnue. Un entier unique forcerait une précision
     * que les sources n'ont pas.
     */
    #[ORM\Column(nullable: true)]
    private ?int $birthAhMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $birthAhMax = null;

    #[ORM\Column(nullable: true)]
    private ?int $deathAhMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $deathAhMax = null;

    /** Transmetteur connu pour dissimuler une rupture de chaîne (tadlīs). */
    #[ORM\Column]
    private bool $mudallis = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tadlisType = null;

    /** Rang de gravité d'Ibn Ḥajar, de 1 à 5. */
    #[ORM\Column(nullable: true)]
    private ?int $tadlisRank = null;

    public function __construct(string $slug, Period $period)
    {
        $this->slug = $slug;
        $this->period = $period;
        $this->names = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /** @return Collection<int, PersonName> */
    public function getNames(): Collection
    {
        return $this->names;
    }

    public function addName(PersonName $name): self
    {
        $this->names->add($name);

        return $this;
    }

    public function removeName(PersonName $name): self
    {
        $this->names->removeElement($name);

        return $this;
    }

    /**
     * Forme d'affichage pour une écriture donnée, avec repli sur la première
     * forme connue de cette écriture.
     */
    public function getDisplayName(NameScript $script = NameScript::Latin): ?string
    {
        $fallback = null;

        foreach ($this->names as $name) {
            if ($name->getScript() !== $script) {
                continue;
            }
            if ($name->isDisplay()) {
                return $name->getForm();
            }
            $fallback ??= $name->getForm();
        }

        return $fallback;
    }

    /**
     * Raccourci pour les gabarits : Twig ne sait pas construire une valeur
     * d'énumération pour l'argument de getDisplayName().
     */
    public function getDisplayNameAr(): ?string
    {
        return $this->getDisplayName(NameScript::Arabic);
    }

    public function getPeriod(): Period
    {
        return $this->period;
    }

    public function setPeriod(Period $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getMeta(): ?string
    {
        return $this->meta;
    }

    public function setMeta(?string $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    public function getWork(): ?string
    {
        return $this->work;
    }

    public function setWork(?string $work): self
    {
        $this->work = $work;

        return $this;
    }

    public function getBirthAhMin(): ?int
    {
        return $this->birthAhMin;
    }

    public function getBirthAhMax(): ?int
    {
        return $this->birthAhMax;
    }

    public function setBirthAh(?int $min, ?int $max = null): self
    {
        $this->birthAhMin = $min;
        $this->birthAhMax = $max ?? $min;

        return $this;
    }

    public function getDeathAhMin(): ?int
    {
        return $this->deathAhMin;
    }

    public function getDeathAhMax(): ?int
    {
        return $this->deathAhMax;
    }

    public function setDeathAh(?int $min, ?int $max = null): self
    {
        $this->deathAhMin = $min;
        $this->deathAhMax = $max ?? $min;

        return $this;
    }

    /**
     * « d. 143 AH » pour une date certaine, « d. 141–150 AH » pour une
     * fourchette, null si la date est inconnue.
     */
    public function getDeathLabel(): ?string
    {
        if (null === $this->deathAhMin) {
            return null;
        }

        return $this->deathAhMin === $this->deathAhMax
            ? \sprintf('d. %d AH', $this->deathAhMin)
            : \sprintf('d. %d–%d AH', $this->deathAhMin, $this->deathAhMax ?? $this->deathAhMin);
    }

    public function isMudallis(): bool
    {
        return $this->mudallis;
    }

    public function setMudallis(bool $mudallis): self
    {
        $this->mudallis = $mudallis;

        return $this;
    }

    public function getTadlisType(): ?string
    {
        return $this->tadlisType;
    }

    public function setTadlisType(?string $tadlisType): self
    {
        $this->tadlisType = $tadlisType;

        return $this;
    }

    public function getTadlisRank(): ?int
    {
        return $this->tadlisRank;
    }

    public function setTadlisRank(?int $tadlisRank): self
    {
        $this->tadlisRank = $tadlisRank;

        return $this;
    }

    public function isCollector(): bool
    {
        return 'collecteur' === $this->period->getId();
    }
}
