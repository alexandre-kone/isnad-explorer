<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\NameKind;
use App\Entity\Enum\NameScript;
use App\Entity\Trait\Curated;
use App\Repository\PersonNameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une forme sous laquelle un transmetteur est cité.
 *
 * Une personne en a 1..N. C'est cette table que la recherche interroge : sans
 * elle, chercher « Abū Ḥanīfa » ne trouverait pas la fiche saisie sous
 * « al-Nuʿmān ibn Thābit », et le curateur en créerait une seconde.
 */
#[ORM\Entity(repositoryClass: PersonNameRepository::class)]
#[ORM\Table(name: 'person_name')]
#[ORM\UniqueConstraint(name: 'uniq_person_name_form', columns: ['person_id', 'form', 'script'])]
#[ORM\Index(name: 'idx_person_name_normalised', columns: ['form_normalised'])]
class PersonName implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'names')]
    #[ORM\JoinColumn(nullable: false)]
    private Person $person;

    /** La forme telle qu'elle est citée, diacritiques compris. */
    #[ORM\Column(length: 255)]
    private string $form;

    /** Clé de recherche, calculée par le normaliseur — jamais saisie. */
    #[ORM\Column(length: 255)]
    private string $formNormalised;

    #[ORM\Column(length: 16, enumType: NameScript::class)]
    private NameScript $script;

    #[ORM\Column(length: 16, enumType: NameKind::class)]
    private NameKind $kind;

    /** Forme retenue pour l'affichage — une seule par personne et par écriture. */
    #[ORM\Column]
    private bool $display = false;

    /** D'où vient cette forme (ouvrage, base externe…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    public function __construct(
        Person $person,
        string $form,
        string $formNormalised,
        NameScript $script,
        NameKind $kind,
    ) {
        $this->person = $person;
        $this->form = $form;
        $this->formNormalised = $formNormalised;
        $this->script = $script;
        $this->kind = $kind;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): self
    {
        $this->person = $person;

        return $this;
    }

    public function getForm(): string
    {
        return $this->form;
    }

    public function getFormNormalised(): string
    {
        return $this->formNormalised;
    }

    public function getScript(): NameScript
    {
        return $this->script;
    }

    public function getKind(): NameKind
    {
        return $this->kind;
    }

    public function setKind(NameKind $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function isDisplay(): bool
    {
        return $this->display;
    }

    public function setDisplay(bool $display): self
    {
        $this->display = $display;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }
}
