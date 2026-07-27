<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CollectionRepository;
use App\Repository\RiwayaReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans de bibliographie : les recueils, leurs éditions, et ce qu'ils
 * rapportent. En lecture seule — la saisie d'un recueil n'a d'intérêt qu'avec
 * les écrans de riwāya (phase 3).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/recueils')]
final class CollectionController extends AbstractController
{
    #[Route('', name: 'admin_collection_index', methods: ['GET'])]
    public function index(CollectionRepository $collections, RiwayaReferenceRepository $references): Response
    {
        return $this->render('admin/collection/index.html.twig', [
            'collections' => $collections->findOrdered(),
            'counts' => $references->countByCollection(),
        ]);
    }

    #[Route('/{slug}', name: 'admin_collection_show', methods: ['GET'])]
    public function show(string $slug, CollectionRepository $collections, RiwayaReferenceRepository $references): Response
    {
        $collection = $collections->findOneBySlug($slug);
        if (null === $collection) {
            throw $this->createNotFoundException('Recueil introuvable.');
        }

        return $this->render('admin/collection/show.html.twig', [
            'collection' => $collection,
            'references' => $references->findByCollection($collection),
        ]);
    }
}
