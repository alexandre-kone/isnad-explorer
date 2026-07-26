<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Nature d'une forme de nom arabe.
 *
 * Un même homme est cité tantôt par sa kunya, tantôt par son ism, et les deux
 * formes peuvent n'avoir aucun caractère commun : Abū Ḥanīfa et al-Nuʿmān ibn
 * Thābit. D'où l'intérêt de qualifier chaque forme.
 */
enum NameKind: string
{
    /** Prénom : al-Nuʿmān. */
    case Ism = 'ism';
    /** Filiation honorifique : Abū Ḥanīfa. */
    case Kunya = 'kunya';
    /** Chaîne de filiation : ibn Thābit ibn Zūṭā. */
    case Nasab = 'nasab';
    /** Rattachement géographique, tribal ou professionnel : al-Ansārī. */
    case Nisba = 'nisba';
    /** Épithète : al-Ṣādiq. */
    case Laqab = 'laqab';
    /** Nom sous lequel la personne est communément connue. */
    case Shuhra = 'shuhra';
    /** Forme longue, combinant plusieurs éléments. */
    case Complete = 'complet';

    public function label(): string
    {
        return match ($this) {
            self::Ism => 'Ism (prénom)',
            self::Kunya => 'Kunya (Abū…)',
            self::Nasab => 'Nasab (filiation)',
            self::Nisba => 'Nisba (rattachement)',
            self::Laqab => 'Laqab (épithète)',
            self::Shuhra => 'Shuhra (nom connu)',
            self::Complete => 'Forme complète',
        };
    }
}
