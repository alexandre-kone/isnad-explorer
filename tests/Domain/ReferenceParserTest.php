<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\ReferenceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceParserTest extends TestCase
{
    private ReferenceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ReferenceParser();
    }

    public function testSimpleReferenceGivesOneCitation(): void
    {
        $citations = $this->parser->parse('Sahîh al-Bukhârî, n°1');

        self::assertCount(1, $citations);
        self::assertSame('Sahîh al-Bukhârî', $citations[0]->title);
        self::assertSame('1', $citations[0]->number);
    }

    /** Le cas qui motive la phase : deux recueils dans une seule colonne. */
    public function testCompositeReferenceGivesOneCitationPerCollection(): void
    {
        $citations = $this->parser->parse('Sahîh al-Bukhârî, n°13 · Muslim, n°45');

        self::assertCount(2, $citations);
        self::assertSame(['Sahîh al-Bukhârî', '13'], [$citations[0]->title, $citations[0]->number]);
        self::assertSame(['Muslim', '45'], [$citations[1]->title, $citations[1]->number]);
    }

    /** Sans cette équivalence, l'import créerait deux recueils pour un seul. */
    #[DataProvider('equivalentTitles')]
    public function testEquivalentTitlesShareOneKey(string $a, string $b): void
    {
        self::assertSame(
            $this->parser->titleKey($a),
            $this->parser->titleKey($b),
            \sprintf('« %s » et « %s » devraient désigner le même recueil.', $a, $b),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentTitles(): iterable
    {
        yield 'genre en tête' => ['Sahîh Muslim', 'Muslim'];
        yield 'article' => ['al-Bukhârî', 'Bukhârî'];
        yield 'genre et article' => ['Sahîh al-Bukhârî', 'Bukhârî'];
        yield 'diacritiques' => ['Abû Dâwûd', 'Abu Dawud'];
        yield 'kunya déclinée' => ['Sunan Abî Dâwûd', 'Abû Dâwûd'];
        yield 'kunya déclinée en second' => ['Musannaf Ibn Abî Shayba', 'Ibn Abû Shayba'];
        yield 'casse' => ['MUSLIM', 'muslim'];
        yield 'espaces multiples' => ['Sunan   al-Nasâʾî', 'Nasai'];
    }

    public function testDistinctCollectionsKeepDistinctKeys(): void
    {
        self::assertNotSame(
            $this->parser->titleKey('Sahîh al-Bukhârî'),
            $this->parser->titleKey('Sahîh Muslim'),
        );

        // Le repliage du génitif ne doit pas s'attaquer à une syllabe interne.
        self::assertNotSame(
            $this->parser->titleKey('Abû Dâwûd'),
            $this->parser->titleKey('al-Rabîʿ'),
        );
    }

    public function testSegmentWithoutNumberKeepsANullNumber(): void
    {
        $citations = $this->parser->parse('Muwattaʾ de Mâlik');

        self::assertCount(1, $citations);
        self::assertSame('Muwattaʾ de Mâlik', $citations[0]->title);
        self::assertNull($citations[0]->number);
    }

    public function testNumberKeepsEditionSuffixes(): void
    {
        $citations = $this->parser->parse('Sahîh Muslim, n°45a · Abû Dâwûd, n°12/3');

        self::assertSame('45a', $citations[0]->number);
        self::assertSame('12/3', $citations[1]->number);
    }

    public function testEmptySegmentsAreIgnored(): void
    {
        self::assertSame([], $this->parser->parse('  ·  '));
        self::assertSame([], $this->parser->parse(''));
    }
}
