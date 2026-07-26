<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue Réseau : la scène de graphe.
 *
 * La page est une coquille SSR ; le graphe lui-même est hydraté par l'îlot
 * Stimulus « network », qui consulte /api/isnad.
 */
final class NetworkController extends AbstractController
{
    #[Route('/reseau', name: 'network', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('network/index.html.twig');
    }
}
