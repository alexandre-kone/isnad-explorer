<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NarratorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un narrateur (rāwī) : maillon d'un isnad. Réutilisable entre hadiths.
 */
#[ORM\Entity(repositoryClass: NarratorRepository::class)]
class Narrator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
