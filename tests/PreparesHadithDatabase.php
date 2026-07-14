<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\HadithFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Prépare une base SQLite de test propre (schéma neuf + fixtures) sur le fichier
 * partagé par le WebTestCase et le serveur Panther (env « test »).
 */
trait PreparesHadithDatabase
{
    protected static function primeHadithDatabase(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $params = $connection->getParams();

        // Repart d'un fichier SQLite vierge pour un schéma déterministe.
        if (($params['driver'] ?? null) === 'pdo_sqlite' && isset($params['path'])) {
            $connection->close();
            if (is_file($params['path'])) {
                unlink($params['path']);
            }
        }

        $tool = new SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        (new HadithFixtures())->load($em);
        $em->clear();
    }
}
