<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Curated;
use Doctrine\ORM\Mapping as ORM;

/**
 * Participation d'une {@see Person} à un {@see Hadith} : ce qui varie d'un
 * hadith à l'autre pour un même transmetteur.
 *
 * Le niveau (0 = Prophète ﷺ) donne la strate verticale dans la vue Réseau.
 */
#[ORM\Entity]
#[ORM\Table(name: 'hadith_person')]
#[ORM\UniqueConstraint(name: 'uniq_hadith_person', columns: ['hadith_id', 'person_id'])]
class HadithParticipant implements CuratedEntity
{
    use Curated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hadith::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private Hadith $hadith;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Person $person;

    #[ORM\Column]
    private int $level;

    /** Nombre de voies en aval, affiché sur le pivot uniquement. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $chains = null;

    /**
     * Notice contextuelle : ce que cette personne fait dans CE hadith.
     * Al-Bukhârî « place ce hadith en ouverture de son Sahîh » pour l'intention,
     * mais « dans le Livre de la foi » pour la fraternité. Écrase {@see Person::getBio()}.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    /** Référence de l'ouvrage pour ce hadith précis, ex. « Sahîh al-Bukhârî, n°13 ». */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $work = null;

    /** Lieu pertinent pour ce hadith, quand il diffère du rattachement habituel. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $region = null;

    public function __construct(Hadith $hadith, Person $person, int $level)
    {
        $this->hadith = $hadith;
        $this->person = $person;
        $this->level = $level;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHadith(): Hadith
    {
        return $this->hadith;
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

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getChains(): ?string
    {
        return $this->chains;
    }

    public function setChains(?string $chains): self
    {
        $this->chains = $chains;

        return $this;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    public function setWork(?string $work): self
    {
        $this->work = $work;

        return $this;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    /** Notice du hadith si elle existe, sinon celle de la personne. */
    public function getBio(): ?string
    {
        return $this->bio ?? $this->person->getBio();
    }

    public function getWork(): ?string
    {
        return $this->work ?? $this->person->getWork();
    }

    public function getRegion(): ?string
    {
        return $this->region ?? $this->person->getRegion();
    }
}
