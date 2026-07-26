<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\PersonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un transmetteur (rāwī), nœud global du graphe.
 *
 * La personne est dédupliquée et partagée par tous les hadiths : c'est ce qui
 * rendra possible, plus tard, les corrélations entre chaînes. Ce qui dépend du
 * hadith (niveau vertical, nombre de voies) vit dans {@see HadithParticipant}.
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

    /** Nom translittéré, ex. « Yahyâ ibn Saʿîd al-Ansârî ». */
    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameAr = null;

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

    public function __construct(string $slug, string $name, Period $period)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->period = $period;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameAr(): ?string
    {
        return $this->nameAr;
    }

    public function setNameAr(?string $nameAr): self
    {
        $this->nameAr = $nameAr;

        return $this;
    }

    public function getPeriod(): Period
    {
        return $this->period;
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

    public function isCollector(): bool
    {
        return 'collecteur' === $this->period->getId();
    }
}
