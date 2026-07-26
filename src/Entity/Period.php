<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PeriodRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Génération de transmetteurs (tabaqa) : Prophète, Compagnons, Successeurs,
 * Suiveurs, Collecteurs. Sert de référentiel de couleurs et d'ordre vertical
 * pour la vue Réseau.
 *
 * La clé est naturelle (« sahaba ») : elle vient du jeu de données et reste
 * stable, ce qui évite un mapping d'identifiants à l'import.
 */
#[ORM\Entity(repositoryClass: PeriodRepository::class)]
#[ORM\Table(name: 'period')]
class Period
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $id;

    #[ORM\Column(length: 64)]
    private string $labelFr;

    #[ORM\Column(length: 64)]
    private string $labelAr;

    /** Couleur hexadécimale du nœud dans le graphe, ex. « #2f6e54 ». */
    #[ORM\Column(length: 7)]
    private string $color;

    /** Rang chronologique (0 = Prophète ﷺ). */
    #[ORM\Column]
    private int $sortOrder;

    public function __construct(string $id, string $labelFr, string $labelAr, string $color, int $sortOrder)
    {
        $this->id = $id;
        $this->labelFr = $labelFr;
        $this->labelAr = $labelAr;
        $this->color = $color;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabelFr(): string
    {
        return $this->labelFr;
    }

    public function getLabelAr(): string
    {
        return $this->labelAr;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
