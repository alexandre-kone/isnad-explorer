<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use App\Repository\HadithClusterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * L'enseignement au sens large : ce qui regroupe les riwāyāt jugées « le même
 * hadith ».
 *
 * Le cluster ne porte aucun texte. Le matn appartient à l'occurrence, parce que
 * chaque voie a sa propre variante : imposer un matn unique par hadith
 * supprimerait l'objet même de l'analyse isnad-cum-matn, qui corrèle les
 * divergences de texte avec celles de chaîne.
 */
#[ORM\Entity(repositoryClass: HadithClusterRepository::class)]
#[ORM\Table(name: 'hadith_cluster')]
class HadithCluster implements CuratedEntity
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $intro = null;

    /** Libellé du nombre de voies, ex. « 6 voies (turuq) ». */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $turuq = null;

    /** Un enseignement non prêt est listé mais pas explorable. */
    #[ORM\Column]
    private bool $ready = true;

    /** @var Collection<int, Riwaya> */
    #[ORM\OneToMany(targetEntity: Riwaya::class, mappedBy: 'cluster', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $riwayat;

    public function __construct(string $slug, string $label)
    {
        $this->slug = $slug;
        $this->label = $label;
        $this->riwayat = new ArrayCollection();
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

    public function setLabel(string $label): self
    {
        $this->label = $label;

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

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): self
    {
        $this->ready = $ready;

        return $this;
    }

    /** @return Collection<int, Riwaya> */
    public function getRiwayat(): Collection
    {
        return $this->riwayat;
    }

    public function addRiwaya(Riwaya $riwaya): self
    {
        if (!$this->riwayat->contains($riwaya)) {
            $this->riwayat->add($riwaya);
        }

        return $this;
    }

    /**
     * La riwāya de référence, tant qu'un cluster n'en a qu'une.
     *
     * Le corpus importé n'en porte qu'une par enseignement : le jeu de données
     * ne distingue ni les matns ni les chaînes voie par voie. Les écrans qui
     * affichent « le » texte passent par ici, et ce qu'ils affichent restera
     * exact quand le curateur aura découpé les voies — c'est simplement la
     * première.
     */
    public function getPrimaryRiwaya(): ?Riwaya
    {
        return $this->riwayat->first() ?: null;
    }
}
