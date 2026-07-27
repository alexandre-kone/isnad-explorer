<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Collection;
use App\Entity\Edition;
use App\Entity\HadithCluster;
use App\Entity\Riwaya;
use App\Entity\RiwayaReference;
use PHPUnit\Framework\TestCase;

final class RiwayaReferenceTest extends TestCase
{
    public function testSameCollectionAndNumberIsRefusedTwice(): void
    {
        $riwaya = $this->riwaya();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');

        $riwaya->addReference(new RiwayaReference($riwaya, $bukhari, '13'));

        $this->expectException(\InvalidArgumentException::class);
        $riwaya->addReference(new RiwayaReference($riwaya, $bukhari, '13'));
    }

    /** Le cas que l'index unique ne couvre pas : deux `NULL` sont distincts en base. */
    public function testSameCollectionWithoutNumberIsRefusedTwice(): void
    {
        $riwaya = $this->riwaya();
        $muwatta = new Collection('muwatta', 'Muwattaʾ de Mâlik', 'malik');

        $riwaya->addReference(new RiwayaReference($riwaya, $muwatta, null));

        $this->expectException(\InvalidArgumentException::class);
        $riwaya->addReference(new RiwayaReference($riwaya, $muwatta, null));
    }

    public function testTwoNumbersInTheSameCollectionAreAccepted(): void
    {
        $riwaya = $this->riwaya();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');

        $riwaya->addReference(new RiwayaReference($riwaya, $bukhari, '13'));
        $riwaya->addReference(new RiwayaReference($riwaya, $bukhari, '45'));

        self::assertCount(2, $riwaya->getReferences());
    }

    public function testAddingTheSameReferenceTwiceIsANoOp(): void
    {
        $riwaya = $this->riwaya();
        $reference = new RiwayaReference($riwaya, new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari'), '13');

        $riwaya->addReference($reference);
        $riwaya->addReference($reference);

        self::assertCount(1, $riwaya->getReferences());
    }

    public function testEditionOfAnotherCollectionIsRefused(): void
    {
        $riwaya = $this->riwaya();
        $bukhari = new Collection('bukhari', 'Sahîh al-Bukhârî', 'bukhari');
        $muslim = new Collection('muslim', 'Sahîh Muslim', 'muslim');

        $reference = new RiwayaReference($riwaya, $bukhari, '13');

        $this->expectException(\InvalidArgumentException::class);
        $reference->setEdition(new Edition($muslim, 'Dâr Ihyâʾ al-Turâth'));
    }

    private function riwaya(): Riwaya
    {
        $cluster = new HadithCluster('intention', 'Hadith de l\'intention');

        return new Riwaya($cluster, 'intention', 'Les actes…', 'Sahîh al-Bukhârî, n°1');
    }
}
