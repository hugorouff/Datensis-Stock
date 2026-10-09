<?php

namespace App\Controller;

use App\Datensis\ArticleRepository;
use App\Datensis\ClientContext;
use App\Form\ArticleType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/articles')]
final class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly ClientContext $clientContext,
    ) {
    }

    #[Route('', name: 'app_articles', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $showInactive = $request->query->getBoolean('inactifs');

        return $this->render('articles/index.html.twig', [
            'available' => $this->articleRepository->isAvailable(),
            'articles' => $this->articleRepository->findByClient($this->clientContext->getClientId(), [
                'q' => $request->query->getString('q'),
                'actifs' => !$showInactive,
            ]),
            'show_inactive' => $showInactive,
            'q' => $request->query->getString('q'),
        ]);
    }

    #[Route('/nouveau', name: 'app_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, null, ['type_article' => 'vente', 'taux_tva' => '20.00', 'suivi_stock' => false]);
    }

    #[Route('/{id}/modifier', name: 'app_article_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $article = $this->findOr404($id);

        return $this->handleForm($request, $article, [
            'code_article' => $article['code'],
            'libelle' => $article['libelle'],
            'description' => $article['description'],
            'categorie_id' => $article['categorie_id'],
            'type_article' => $article['type'],
            'unite' => $article['unite'],
            'prix_ht' => number_format($article['prix_ht'], 6, '.', ''),
            'prix_achat' => null !== $article['prix_achat'] ? number_format($article['prix_achat'], 2, '.', '') : null,
            'taux_tva' => null !== $article['taux_tva'] ? number_format($article['taux_tva'], 2, '.', '') : null,
            'suivi_stock' => $article['suivi_stock'],
            'stock_minimum' => null !== $article['stock_minimum'] ? (string) $article['stock_minimum'] : null,
            'stock_critique' => null !== $article['stock_critique'] ? (string) $article['stock_critique'] : null,
        ]);
    }

    #[Route('/{id}/statut', name: 'app_article_toggle', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function toggle(Request $request, int $id): Response
    {
        $article = $this->findOr404($id);

        if (!$this->isCsrfTokenValid('article-toggle-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->articleRepository->setActive($this->clientContext->getClientId(), $id, !$article['actif']);
        $this->addFlash('success', \sprintf('Article %s %s.', $article['code'], $article['actif'] ? 'désactivé' : 'réactivé'));

        return $this->redirectToRoute('app_articles', $article['actif'] ? [] : ['inactifs' => 1]);
    }

    /**
     * @param array<string, mixed>|null $article
     * @param array<string, mixed>      $data
     */
    private function handleForm(Request $request, ?array $article, array $data): Response
    {
        $clientId = $this->clientContext->getClientId();
        $vatRates = ArticleType::VAT_RATES;
        if (null !== $data['taux_tva'] && !\in_array($data['taux_tva'], $vatRates, true)) {
            $vatRates[str_replace('.', ',', rtrim(rtrim($data['taux_tva'], '0'), '.')).' %'] = $data['taux_tva'];
        }

        $form = $this->createForm(ArticleType::class, $data, [
            'categories' => $this->articleRepository->categories($clientId),
            'vat_rates' => $vatRates,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (null === $article) {
                    $this->articleRepository->create($clientId, $form->getData());
                    $this->addFlash('success', 'Article ajouté au catalogue.');
                } else {
                    $this->articleRepository->update($clientId, $article['id'], $form->getData());
                    $this->addFlash('success', 'Article mis à jour.');
                }

                return $this->redirectToRoute('app_articles', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('articles/form.html.twig', [
            'form' => $form,
            'article' => $article,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(int $id): array
    {
        return $this->articleRepository->find($this->clientContext->getClientId(), $id)
            ?? throw $this->createNotFoundException('Article introuvable pour cette société.');
    }
}
