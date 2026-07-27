<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Découpe une référence composite en citations exploitables.
 *
 * `Riwaya.reference` est un VARCHAR unique où l'on a concaténé une liste faute
 * de pouvoir l'exprimer : « Sahîh al-Bukhârî, n°13 · Muslim, n°45 ». Ce parseur
 * est ce qui la rend structurable.
 *
 * Fonction pure : aucune dépendance, entièrement testable unitairement.
 */
final class ReferenceParser
{
    private const string SEPARATORS = '/[·;]/u';

    /** Le numéro tolère lettre et barre : les éditions numérotent parfois « 45a » ou « 12/3 ». */
    private const string CITATION = '/^(?<title>.+?)\s*,?\s*n\s*[°º]\s*(?<number>[\p{L}\p{N}\/-]+)\s*$/u';

    /** Mots de genre en tête de titre : ils décrivent le recueil, ils ne l'identifient pas. */
    private const array GENRE_PREFIXES = [
        'sahih', 'sunan', 'musnad', 'jami', 'muwatta', 'mustadrak', 'musannaf',
    ];

    private const array STRIPPED = ["\u{02BF}", "\u{02BE}", "'", '`', '’', '‘'];

    /**
     * @return list<Citation>
     */
    public function parse(string $reference): array
    {
        $citations = [];

        foreach (preg_split(self::SEPARATORS, $reference) ?: [] as $segment) {
            $segment = trim($segment);
            if ('' === $segment) {
                continue;
            }

            [$title, $number] = $this->split($segment);
            $citations[] = new Citation($title, $this->titleKey($title), $number);
        }

        return $citations;
    }

    /**
     * Ramène un titre à sa clé d'identité.
     *
     * Le repliage latin ressemble à celui de {@see PersonNameNormaliser} sans
     * être mutualisé : l'un fabrique une clé de recherche sur des noms, l'autre
     * une clé d'identité de recueil. Les coupler ferait qu'un ajustement de la
     * recherche déplacerait silencieusement l'identité des recueils.
     */
    public function titleKey(string $title): string
    {
        $decomposed = \Normalizer::normalize($title, \Normalizer::FORM_D);
        if (false === $decomposed) {
            $decomposed = $title;
        }

        $value = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        $value = str_replace(self::STRIPPED, '', $value);
        $value = mb_strtolower($value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        // Le nom qui suit le mot de genre est au génitif : « Sunan Abî Dâwûd »
        // désigne le recueil que le nom seul cite « Abû Dâwûd ».
        $value = (string) preg_replace('/\b(?:abi|aba)\b/u', 'abu', $value);

        foreach (self::GENRE_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix.' ')) {
                $value = substr($value, \strlen($prefix) + 1);
                break;
            }
        }

        if (str_starts_with($value, 'al-')) {
            $value = substr($value, 3);
        }

        return $value;
    }

    /**
     * Un segment sans numéro reconnaissable reste un titre : deviner un numéro
     * serait inventer une donnée.
     *
     * @return array{0: string, 1: string|null}
     */
    private function split(string $segment): array
    {
        if (1 === preg_match(self::CITATION, $segment, $matches)) {
            return [trim($matches['title'], " \t\n\r\0\x0B,"), $matches['number']];
        }

        return [$segment, null];
    }
}
