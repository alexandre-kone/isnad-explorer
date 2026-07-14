<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\HadithSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/recherche', name: 'search', methods: ['GET'])]
    public function search(Request $request, HadithSearch $search): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $results = $search->byMatn($query);

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
