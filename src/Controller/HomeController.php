<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\HelloService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(HelloService $hello): Response
    {
        return $this->render('home/index.html.twig', [
            'message' => $hello->message(),
        ]);
    }
}
