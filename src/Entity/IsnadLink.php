<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Maillon d'un isnad : associe un {@see Narrator} à un {@see Hadith} à une
 * position donnée dans la chaîne de transmission.
 */
#[ORM\Entity]
#[ORM\Table(name: 'isnad_link')]
class IsnadLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hadith::class, inversedBy: 'isnadLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private Hadith $hadith;

    #[ORM\ManyToOne(targetEntity: Narrator::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Narrator $narrator;

    /** Rang dans la chaîne (0 = premier transmetteur). */
    #[ORM\Column]
    private int $position;

    public function __construct(Hadith $hadith, Narrator $narrator, int $position)
    {
        $this->hadith = $hadith;
        $this->narrator = $narrator;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHadith(): Hadith
    {
        return $this->hadith;
    }

    public function getNarrator(): Narrator
    {
        return $this->narrator;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
