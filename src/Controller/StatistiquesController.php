<?php

namespace App\Controller;

use App\Datensis\ClientContext;
use App\Datensis\Statistics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatistiquesController extends AbstractController
{
    #[Route('/statistiques', name: 'app_statistiques', methods: ['GET'])]
    public function index(ClientContext $clientContext, Statistics $statistics): Response
    {
        return $this->render('statistiques/index.html.twig', [
            'stats' => $statistics->forClient($clientContext->getClientId()),
        ]);
    }
}
