<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entité saisie par un curateur : porte son horodatage, son attribution et un
 * numéro de version pour le verrou optimiste.
 *
 * L'interface existe pour que {@see \App\EventListener\CuratedEntityListener}
 * reconnaisse ces entités par un simple `instanceof`, plutôt qu'en inspectant
 * les traits utilisés. Les implémentations tirent leurs champs du trait
 * {@see \App\Entity\Trait\Curated}.
 */
interface CuratedEntity
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void;

    public function getCreatedBy(): ?User;

    public function setCreatedBy(?User $createdBy): void;

    public function getUpdatedBy(): ?User;

    public function setUpdatedBy(?User $updatedBy): void;

    public function getVersion(): int;
}
