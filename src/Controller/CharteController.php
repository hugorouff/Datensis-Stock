<?php

declare(strict_types=1);

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
    public function index(
        Request $request,
        ClientContext $clientContext,
        BrandingRepository $brandingRepository,
    ): Response {
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
            $logoPath = null;

            // Le nouveau fichier est cree sans effacer l'ancien.
            if ($data['logo_fichier'] instanceof UploadedFile) {
                $logoPath = $this->storeLogo($clientId, $data['logo_fichier']);
            }

            try {
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
            } catch (\Throwable $exception) {
                // Si la BDD echoue, on supprime uniquement le nouveau fichier.
                if (null !== $logoPath) {
                    $this->deleteUploadedLogo($logoPath);
                }
                throw $exception;
            }

            if (null !== $logoPath) {
                // Le nouvel enregistrement en BDD est valide : on nettoie les anciens logos.
                $this->removeStoredLogos($clientId, $logoPath);
            }

            $this->addFlash('success', 'Charte graphique enregistree pour tous les utilisateurs de la societe.');

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
    public function reset(
        Request $request,
        ClientContext $clientContext,
        BrandingRepository $brandingRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('charte-reset', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clientId = $clientContext->getClientId();

        // La BDD d'abord : un echec ne doit pas supprimer le logo existant.
        $brandingRepository->save(
            $clientId,
            $clientContext->getClientName(),
            Branding::DEFAULT_PRIMARY,
            Branding::DEFAULT_SECONDARY,
            null,
            Branding::DEFAULT_ACCENT,
            Branding::DEFAULT_BACKGROUND,
            Branding::DEFAULT_NAVBAR,
            Branding::DEFAULT_CARD,
            null,
            true,
        );
        $this->removeStoredLogos($clientId);

        $this->addFlash('success', 'Couleurs par defaut retablies et logo retire pour la societe.');

        return $this->redirectToRoute('app_charte', [], Response::HTTP_SEE_OTHER);
    }

    private function storeLogo(int $clientId, UploadedFile $file): string
    {
        $directory = $this->getParameter('kernel.project_dir').'/public/uploads/charte/'.$clientId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de creer le dossier du logo.');
        }

        $extension = LogoImage::extension($file)
            ?? throw new \RuntimeException('Format de logo non reconnu.');

        // Nom unique : aucune perte de l'ancien fichier en cas d'echec SQL.
        $filename = 'logo-'.bin2hex(random_bytes(12)).'.'.$extension;
        $file->move($directory, $filename);

        return 'charte/'.$clientId.'/'.$filename;
    }

    private function removeStoredLogos(int $clientId, ?string $keep = null): void
    {
        $directory = $this->getParameter('kernel.project_dir').'/public/uploads/charte/'.$clientId;
        $keepName = null !== $keep ? basename($keep) : null;

        foreach (glob($directory.'/logo*.*') ?: [] as $existing) {
            if (null !== $keepName && basename($existing) === $keepName) {
                continue;
            }
            if (is_file($existing)) {
                unlink($existing);
            }
        }
    }

    private function deleteUploadedLogo(string $relativePath): void
    {
        // Cette methode ne recoit que des chemins generes par storeLogo().
        $file = $this->getParameter('kernel.project_dir').'/public/uploads/'.$relativePath;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
