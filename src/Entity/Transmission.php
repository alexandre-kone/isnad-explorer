<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use Doctrine\ORM\Mapping as ORM;

/**
 * Arête du graphe : « untel a transmis à untel », dans le contexte d'une riwāya.
 *
 * Le sens va du maître (amont) vers l'élève (aval), comme dans un isnad lu du
 * Prophète ﷺ vers le compilateur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'transmission')]
#[ORM\UniqueConstraint(name: 'uniq_transmission', columns: ['riwaya_id', 'from_person_id', 'to_person_id'])]
#[ORM\Index(name: 'idx_transmission_from', columns: ['from_person_id'])]
#[ORM\Index(name: 'idx_transmission_to', columns: ['to_person_id'])]
class Transmission implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Riwaya::class, inversedBy: 'transmissions')]
    #[ORM\JoinColumn(nullable: false)]
    private Riwaya $riwaya;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(name: 'from_person_id', nullable: false)]
    private Person $from;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(name: 'to_person_id', nullable: false)]
    private Person $to;

    /**
     * Arête de l'épine dorsale gharîb : le segment où la chaîne est unique,
     * avant que l'isnad ne s'évente. Rendu en or épais dans la vue Réseau.
     */
    #[ORM\Column]
    private bool $spine = false;

    public function __construct(Riwaya $riwaya, Person $from, Person $to, bool $spine = false)
    {
        $this->riwaya = $riwaya;
        $this->from = $from;
        $this->to = $to;
        $this->spine = $spine;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiwaya(): Riwaya
    {
        return $this->riwaya;
    }

    public function getFrom(): Person
    {
        return $this->from;
    }

    public function getTo(): Person
    {
        return $this->to;
    }

    public function setFrom(Person $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function setTo(Person $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function isSpine(): bool
    {
        return $this->spine;
    }

    public function setSpine(bool $spine): self
    {
        $this->spine = $spine;

        return $this;
    }
}
