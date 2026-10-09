<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AideController extends AbstractController
{
    #[Route('/aide', name: 'app_aide', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('aide/index.html.twig');
    }
}
