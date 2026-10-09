<?php

namespace App\Controller;

use App\Datensis\Branding;
use App\Datensis\BrandingRepository;
use App\Datensis\ClientContext;
use App\Form\CharteType;
use App\Form\LogoImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CharteController extends AbstractController
{
    #[Route('/charte', name: 'app_charte', methods: ['GET', 'POST'])]
    public function index(Request $request, ClientContext $clientContext, BrandingRepository $brandingRepository): Response
    {
        $clientId = $clientContext->getClientId();
        $branding = $brandingRepository->forClient($clientId, $clientContext->getClientName());

        $form = $this->createForm(CharteType::class, [
            'couleur_principale' => $branding->primaryColor,
            'couleur_secondaire' => $branding->secondaryColor,
            'couleur_accent' => $branding->accentColor,
            'couleur_fond' => $branding->backgroundColor,
            'couleur_navbar' => $branding->navbarColor,
            'couleur_carte' => $branding->cardColor,
            'logo_url' => $branding->externalLogoUrl,
        ], ['disabled' => !$brandingRepository->isAvailable()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $logoPath = $data['logo_fichier'] instanceof UploadedFile ? $this->storeLogo($clientId, $data['logo_fichier']) : null;
            $brandingRepository->save(
                $clientId,
                $clientContext->getClientName(),
                Branding::normalizeColor($data['couleur_principale'], Branding::DEFAULT_PRIMARY),
                Branding::normalizeColor($data['couleur_secondaire'], Branding::DEFAULT_SECONDARY),
                $data['logo_url'],
                Branding::normalizeColor($data['couleur_accent'], Branding::DEFAULT_ACCENT),
                Branding::normalizeColor($data['couleur_fond'], Branding::DEFAULT_BACKGROUND),
                Branding::normalizeColor($data['couleur_navbar'], Branding::DEFAULT_NAVBAR),
                Branding::normalizeColor($data['couleur_carte'], Branding::DEFAULT_CARD),
                $logoPath,
            );
            $this->addFlash('success', 'Charte graphique enregistrée pour tous les utilisateurs de la société.');

            return $this->redirectToRoute('app_charte', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('charte/index.html.twig', [
            'form' => $form,
            'branding' => $branding,
            'palettes' => Branding::PALETTES,
            'available' => $brandingRepository->isAvailable(),
        ]);
    }

    #[Route('/charte/reinitialiser', name: 'app_charte_reset', methods: ['POST'])]
    public function reset(Request $request, ClientContext $clientContext, BrandingRepository $brandingRepository): Response
    {
        if (!$this->isCsrfTokenValid('charte-reset', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clientId = $clientContext->getClientId();
        $this->removeStoredLogo($clientId);
        $brandingRepository->save($clientId, $clientContext->getClientName(), Branding::DEFAULT_PRIMARY, Branding::DEFAULT_SECONDARY, null, Branding::DEFAULT_ACCENT, Branding::DEFAULT_BACKGROUND, Branding::DEFAULT_NAVBAR, Branding::DEFAULT_CARD, null, true);
        $this->addFlash('success', 'Couleurs par défaut rétablies et logo retiré pour la société.');

        return $this->redirectToRoute('app_charte', [], Response::HTTP_SEE_OTHER);
    }

    private function storeLogo(int $clientId, UploadedFile $file): string
    {
        $this->removeStoredLogo($clientId);
        $directory = $this->getParameter('kernel.project_dir').'/public/uploads/charte/'.$clientId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier du logo.');
        }

        $extension = LogoImage::extension($file) ?? throw new \RuntimeException('Format de logo non reconnu.');
        $file->move($directory, 'logo.'.$extension);

        return 'charte/'.$clientId.'/logo.'.$extension;
    }

    private function removeStoredLogo(int $clientId): void
    {
        $directory = $this->getParameter('kernel.project_dir').'/public/uploads/charte/'.$clientId;
        foreach (glob($directory.'/logo.*') ?: [] as $existing) {
            unlink($existing);
        }
    }
}
