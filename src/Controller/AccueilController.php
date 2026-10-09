<?php

namespace App\Controller;

use App\Datensis\ClientContext;
use App\Datensis\Statistics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_accueil');
    }

    #[Route('/accueil', name: 'app_accueil')]
    public function index(ClientContext $clientContext, Statistics $statistics): Response
    {
        return $this->render('accueil/index.html.twig', [
            'stats' => $statistics->forClient($clientContext->getClientId()),
        ]);
    }
}
