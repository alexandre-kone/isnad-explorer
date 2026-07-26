<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\IsnadDatasetLoader;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge le jeu d'isnads versionné (data/isnad/wireframe.json).
 *
 * Les données ne sont plus écrites en dur ici : elles viennent du même fichier
 * que la commande d'import, pour que fixtures de test et base de dev décrivent
 * exactement le même corpus.
 */
final class HadithFixtures extends Fixture
{
    public function __construct(private readonly IsnadDatasetLoader $loader)
    {
    }

    public function load(ObjectManager $manager): void
    {
        \assert($manager instanceof EntityManagerInterface);

        $this->loader->load($manager);
    }
}
