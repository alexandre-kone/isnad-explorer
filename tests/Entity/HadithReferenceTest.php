<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Collection;
use App\Entity\Edition;
use App\Entity\Hadith;
use App\Entity\HadithReference;
use PHPUnit\Framework\TestCase;

final class HadithReferenceTest extends TestCase
{
    public function testSameCollectionAndNumberIsRefusedTwice(): void
    {
        $hadith = $this->hadith();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');

        $hadith->addReference(new HadithReference($hadith, $bukhari, '13'));

        $this->expectException(\InvalidArgumentException::class);
        $hadith->addReference(new HadithReference($hadith, $bukhari, '13'));
    }

    /** Le cas que l'index unique ne couvre pas : deux `NULL` sont distincts en base. */
    public function testSameCollectionWithoutNumberIsRefusedTwice(): void
    {
        $hadith = $this->hadith();
        $muwatta = new Collection('muwatta', 'Muwattaʾ de Mâlik', 'malik');

        $hadith->addReference(new HadithReference($hadith, $muwatta, null));

        $this->expectException(\InvalidArgumentException::class);
        $hadith->addReference(new HadithReference($hadith, $muwatta, null));
    }

    public function testTwoNumbersInTheSameCollectionAreAccepted(): void
    {
        $hadith = $this->hadith();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');

        $hadith->addReference(new HadithReference($hadith, $bukhari, '13'));
        $hadith->addReference(new HadithReference($hadith, $bukhari, '45'));

        self::assertCount(2, $hadith->getReferences());
    }

    public function testAddingTheSameReferenceTwiceIsANoOp(): void
    {
        $hadith = $this->hadith();
        $reference = new HadithReference($hadith, new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari'), '13');

        $hadith->addReference($reference);
        $hadith->addReference($reference);

        self::assertCount(1, $hadith->getReferences());
    }

    public function testEditionOfAnotherCollectionIsRefused(): void
    {
        $hadith = $this->hadith();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');
        $muslim = new Collection('muslim', 'Sahîh Muslim', 'muslim');

        $reference = new HadithReference($hadith, $bukhari, '13');

        $this->expectException(\InvalidArgumentException::class);
        $reference->setEdition(new Edition($muslim, 'Dâr Ihyâʾ al-Turâth'));
    }

    private function hadith(): Hadith
    {
        return new Hadith('intention', 'Hadith de l\'intention', 'Les actes…', 'Sahîh al-Bukhârî, n°1');
    }
}
