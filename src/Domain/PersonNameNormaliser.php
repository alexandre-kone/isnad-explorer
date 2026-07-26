<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Enum\NameScript;

/**
 * Ramène une forme de nom à une clé de recherche stable.
 *
 * Sans cette normalisation, « يحيى » et « يحيي » sont deux chaînes distinctes,
 * et « Yahyâ » ne trouve pas « Yahya » : le curateur ne retrouve pas ce qu'il a
 * saisi la semaine précédente, et crée un doublon.
 *
 * Fonction pure : aucune dépendance, entièrement testable unitairement.
 */
final class PersonNameNormaliser
{
    /** Signes vocaliques et diacritiques arabes (tashkīl). */
    private const array ARABIC_DIACRITICS = [
        "\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}",
        "\u{0651}", "\u{0652}", "\u{0653}", "\u{0654}", "\u{0655}", "\u{0670}",
    ];

    /** Variantes graphiques ramenées à une forme unique. */
    private const array ARABIC_FOLDING = [
        "\u{0622}" => "\u{0627}", // آ → ا
        "\u{0623}" => "\u{0627}", // أ → ا
        "\u{0625}" => "\u{0627}", // إ → ا
        "\u{0671}" => "\u{0627}", // ٱ → ا
        "\u{0629}" => "\u{0647}", // ة → ه
        "\u{0649}" => "\u{064A}", // ى → ي
        "\u{0624}" => "\u{0648}", // ؤ → و
        "\u{0626}" => "\u{064A}", // ئ → ي
    ];

    /** Lettres de prolongation et marques de translittération à retirer. */
    private const array STRIPPED = [
        "\u{0640}", // tatwīl ـ
        "\u{02BF}", // ʿ ayn
        "\u{02BE}", // ʾ hamza
        "'", '`', '’', '‘',
    ];

    public function normalise(string $form, NameScript $script): string
    {
        $value = trim($form);

        $value = NameScript::Arabic === $script
            ? $this->normaliseArabic($value)
            : $this->normaliseLatin($value);

        // Espaces multiples réduits en fin de traitement : les substitutions
        // précédentes peuvent en laisser.
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normaliseArabic(string $value): string
    {
        $value = str_replace(self::ARABIC_DIACRITICS, '', $value);
        $value = strtr($value, self::ARABIC_FOLDING);

        return str_replace(self::STRIPPED, '', $value);
    }

    private function normaliseLatin(string $value): string
    {
        // Décomposition canonique puis suppression des marques combinantes :
        // « â » devient « a » sans table de correspondance à maintenir.
        $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
        if (false === $decomposed) {
            $decomposed = $value;
        }

        $value = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        $value = str_replace(self::STRIPPED, '', $value);

        return mb_strtolower($value);
    }
}
