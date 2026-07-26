<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\HadithRepository;
use App\Repository\PersonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accueil de l'espace de curation.
 *
 * Les écrans de saisie viendront avec les phases suivantes ; cette page sert
 * pour l'instant de point d'entrée authentifié et de tableau de bord minimal.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_home', methods: ['GET'])]
    public function index(PersonRepository $people, HadithRepository $hadiths): Response
    {
        return $this->render('admin/index.html.twig', [
            'personCount' => $people->count([]),
            'hadithCount' => $hadiths->count([]),
        ]);
    }
}
