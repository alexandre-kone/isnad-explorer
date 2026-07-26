<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Horodatage, attribution et verrou optimiste des entités curatées.
 *
 * Un trait plutôt qu'une MappedSuperclass : les entités n'héritent de rien, et
 * un trait se compose sans contraindre la hiérarchie.
 *
 * `createdBy` est **nullable**, contrairement à ce que prévoyait la
 * spécification initiale : les données chargées par import ou par fixtures ne
 * sont créées par aucun curateur. NULL signifie donc « import / système », ce
 * qui est une information exacte plutôt qu'une attribution fictive.
 */
trait Curated
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    /**
     * Verrou optimiste. Doctrine incrémente la valeur à chaque écriture et
     * refuse une mise à jour fondée sur une version périmée.
     *
     * Le type de colonne est explicite : sans lui, la propriété nullable fait
     * échouer l'inférence de Doctrine, qui n'accepte qu'un entier ou une date
     * comme champ de version.
     */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
