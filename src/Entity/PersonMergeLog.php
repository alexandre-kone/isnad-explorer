<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'une fusion de fiches.
 *
 * La fusion est l'opération la plus destructrice de l'outil : elle repointe des
 * chaînes entières vers une autre fiche et supprime l'absorbée. Le journal
 * permet de défaire une fusion erronée et, à plusieurs curateurs, de savoir à
 * qui en parler.
 */
#[ORM\Entity]
#[ORM\Table(name: 'person_merge_log')]
class PersonMergeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** La fiche absorbée n'existe plus : on conserve sa clé et son libellé. */
    #[ORM\Column(length: 64)]
    private string $absorbedSlug;

    #[ORM\Column(length: 255)]
    private string $absorbedLabel;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Person $kept = null;

    /**
     * Formes de noms transférées, pour pouvoir reconstituer la fiche absorbée.
     *
     * @var list<array{form: string, script: string, kind: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $transferredNames = [];

    /** Ce que la fusion a touché, pour mesurer l'ampleur après coup. */
    #[ORM\Column(type: 'json')]
    private array $impact = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $mergedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $mergedAt;

    /**
     * @param list<array{form: string, script: string, kind: string}> $transferredNames
     * @param array<string, int>                                     $impact
     */
    public function __construct(
        string $absorbedSlug,
        string $absorbedLabel,
        ?Person $kept,
        array $transferredNames,
        array $impact,
        ?User $mergedBy,
    ) {
        $this->absorbedSlug = $absorbedSlug;
        $this->absorbedLabel = $absorbedLabel;
        $this->kept = $kept;
        $this->transferredNames = $transferredNames;
        $this->impact = $impact;
        $this->mergedBy = $mergedBy;
        $this->mergedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbsorbedSlug(): string
    {
        return $this->absorbedSlug;
    }

    public function getAbsorbedLabel(): string
    {
        return $this->absorbedLabel;
    }

    public function getKept(): ?Person
    {
        return $this->kept;
    }

    /** @return list<array{form: string, script: string, kind: string}> */
    public function getTransferredNames(): array
    {
        return $this->transferredNames;
    }

    /** @return array<string, int> */
    public function getImpact(): array
    {
        return $this->impact;
    }

    public function getMergedBy(): ?User
    {
        return $this->mergedBy;
    }

    public function getMergedAt(): \DateTimeImmutable
    {
        return $this->mergedAt;
    }
}
