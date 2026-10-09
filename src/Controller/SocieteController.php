<?php

namespace App\Controller;

use App\Datensis\ClientContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SocieteController extends AbstractController
{
    #[Route('/societe', name: 'app_societe_switch', methods: ['POST'])]
    public function switch(Request $request, ClientContext $clientContext): Response
    {
        if (!$this->isCsrfTokenValid('switch-societe', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clientContext->switchTo($request->getPayload()->getInt('client'));
        $this->addFlash('success', \sprintf('Vous consultez maintenant %s.', $clientContext->getClientName()));

        $target = $request->headers->get('referer');

        return null !== $target && str_starts_with($target, $request->getSchemeAndHttpHost())
            ? $this->redirect($target)
            : $this->redirectToRoute('app_accueil');
    }
}
