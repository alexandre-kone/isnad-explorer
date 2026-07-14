<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Hadith;
use App\Entity\Narrator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de démonstration : hadiths réels (matn en traduction française) avec leur
 * isnad authentique, ordonné du Compagnon (position 0) au maître direct du
 * compilateur. Les narrateurs communs sont mutualisés entre chaînes.
 */
final class HadithFixtures extends Fixture
{
    /** @var array<string, Narrator> */
    private array $narrators = [];

    public function load(ObjectManager $manager): void
    {
        // Sahih al-Bukhari 1 — Le hadith de l'intention.
        $this->hadith(
            $manager,
            "Les actes ne valent que par les intentions, et chacun n'a pour lui que ce qu'il a eu l'intention de faire.",
            'Sahih al-Bukhari 1',
            [
                'Umar ibn al-Khattab',
                'Alqama ibn Waqqas al-Laythi',
                'Muhammad ibn Ibrahim al-Taymi',
                'Yahya ibn Saʿid al-Ansari',
                'Sufyan ibn ʿUyayna',
                'Abdullah ibn al-Zubayr al-Humaydi',
            ],
        );

        // Sahih al-Bukhari 13 — L'amour pour son frère.
        $this->hadith(
            $manager,
            "Aucun de vous ne croit véritablement tant qu'il n'aime pas pour son frère ce qu'il aime pour lui-même.",
            'Sahih al-Bukhari 13',
            [
                'Anas ibn Malik',
                'Qatada ibn Diʿama',
                'Shuʿba ibn al-Hajjaj',
                'Yahya ibn Saʿid al-Qattan',
                'Musaddad ibn Musarhad',
            ],
        );

        // Sahih al-Bukhari 8 — Les cinq piliers de l'Islam.
        $this->hadith(
            $manager,
            "L'Islam est bâti sur cinq piliers : l'attestation de foi, la prière, l'aumône légale, le jeûne du Ramadan et le pèlerinage à la Maison sacrée.",
            'Sahih al-Bukhari 8',
            [
                'Abdullah ibn Umar',
                'Ikrima ibn Khalid',
                'Hanzala ibn Abi Sufyan',
                'Ubaydullah ibn Musa',
            ],
        );

        // Sahih al-Bukhari 6018 — Parler en bien ou se taire.
        $this->hadith(
            $manager,
            "Que celui qui croit en Allah et au Jour dernier dise du bien ou se taise ; qu'il honore son voisin ; qu'il honore son hôte.",
            'Sahih al-Bukhari 6018',
            [
                'Abu Hurayra',
                'Abu Salih al-Samman',
                'Abu Hasin al-Asadi',
                'Abu al-Ahwas Sallam ibn Sulaym',
                'Qutayba ibn Saʿid',
            ],
        );

        $manager->flush();
    }

    /**
     * @param list<string> $isnad chaîne ordonnée (Compagnon → maître du compilateur)
     */
    private function hadith(ObjectManager $manager, string $matn, string $reference, array $isnad): void
    {
        $hadith = new Hadith($matn, $reference);

        foreach ($isnad as $name) {
            $hadith->addNarrator($this->narrator($manager, $name));
        }

        $manager->persist($hadith);
    }

    private function narrator(ObjectManager $manager, string $name): Narrator
    {
        if (!isset($this->narrators[$name])) {
            $narrator = new Narrator($name);
            $manager->persist($narrator);
            $this->narrators[$name] = $narrator;
        }

        return $this->narrators[$name];
    }
}
