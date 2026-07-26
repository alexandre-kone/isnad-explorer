<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\PersonNameNormaliser;
use App\Entity\Enum\NameScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le normaliseur décide si la recherche fonctionne : deux formes qui désignent
 * le même nom doivent produire la même clé, sinon le curateur ne retrouve pas
 * sa saisie et crée un doublon.
 */
final class PersonNameNormaliserTest extends TestCase
{
    private PersonNameNormaliser $normaliser;

    protected function setUp(): void
    {
        $this->normaliser = new PersonNameNormaliser();
    }

    /**
     * @param NameScript::* $script
     */
    #[DataProvider('equivalentForms')]
    public function testEquivalentFormsShareOneKey(string $a, string $b, NameScript $script): void
    {
        self::assertSame(
            $this->normaliser->normalise($a, $script),
            $this->normaliser->normalise($b, $script),
            \sprintf('« %s » et « %s » devraient produire la même clé.', $a, $b),
        );
    }

    /**
     * @return iterable<string, array{string, string, NameScript}>
     */
    public static function equivalentForms(): iterable
    {
        // Arabe
        yield 'alif maqsura vs ya' => ['يحيى', 'يحيي', NameScript::Arabic];
        yield 'hamza sur alif' => ['أحمد', 'احمد', NameScript::Arabic];
        yield 'alif madda' => ['آدم', 'ادم', NameScript::Arabic];
        yield 'ta marbuta vs ha' => ['عائشة', 'عائشه', NameScript::Arabic];
        yield 'tashkil ignoré' => ['مُحَمَّد', 'محمد', NameScript::Arabic];
        yield 'tatwil ignoré' => ['محـــمد', 'محمد', NameScript::Arabic];

        // Latin
        yield 'diacritiques' => ['Yahyâ', 'Yahya', NameScript::Latin];
        yield 'casse' => ['MÂLIK', 'malik', NameScript::Latin];
        yield 'ayn' => ['Saʿîd', 'Said', NameScript::Latin];
        yield 'hamza latine' => ['Nasâʾî', 'Nasai', NameScript::Latin];
        yield 'espaces multiples' => ['Ibn   ʿUyayna', 'ibn uyayna', NameScript::Latin];
    }

    public function testDistinctNamesKeepDistinctKeys(): void
    {
        $thawri = $this->normaliser->normalise('Sufyân al-Thawrî', NameScript::Latin);
        $uyayna = $this->normaliser->normalise('Sufyân ibn ʿUyayna', NameScript::Latin);

        self::assertNotSame($thawri, $uyayna);
    }

    public function testResultIsIdempotent(): void
    {
        $once = $this->normaliser->normalise('Yahyâ ibn Saʿîd al-Ansârî', NameScript::Latin);
        $twice = $this->normaliser->normalise($once, NameScript::Latin);

        self::assertSame($once, $twice);
    }
}
