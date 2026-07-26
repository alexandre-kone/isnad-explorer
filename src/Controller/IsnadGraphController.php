<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\IsnadGraph;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expose le graphe de transmission à la vue Réseau.
 *
 * Le corpus tient en quelques dizaines de nœuds : il est servi en une fois,
 * ce qui permet au sélecteur de hadith de basculer sans nouvel aller-retour.
 */
final class IsnadGraphController extends AbstractController
{
    #[Route('/api/isnad', name: 'api_isnad', methods: ['GET'])]
    public function graph(IsnadGraph $graph): JsonResponse
    {
        return $this->json($graph->payload());
    }
}
