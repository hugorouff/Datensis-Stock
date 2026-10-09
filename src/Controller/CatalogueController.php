<?php

namespace App\Controller;

use App\Datensis\ArticleRepository;
use App\Datensis\ClientContext;
use App\Datensis\StockLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/catalogue')]
final class CatalogueController extends AbstractController
{
    /** @var array<string, string> */
    private const SORTS = [
        'categorie' => 'Catégorie',
        'libelle' => 'Libellé',
        'code' => 'Référence',
        'prix' => 'Prix croissant',
        'prix_desc' => 'Prix décroissant',
        'stock' => 'État de stock',
    ];

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly ClientContext $clientContext,
    ) {
    }

    #[Route('', name: 'app_catalogue', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return $this->render('catalogue/index.html.twig', [
            'available' => $this->articleRepository->isAvailable(),
            'articles' => $this->articleRepository->findByClient($this->clientContext->getClientId(), $filters),
            'categories' => $this->articleRepository->categories($this->clientContext->getClientId()),
            'stock_levels' => StockLevel::cases(),
            'sorts' => self::SORTS,
            'filters' => $filters,
        ]);
    }

    #[Route('/export.csv', name: 'app_catalogue_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse
    {
        $articles = $this->articleRepository->findByClient($this->clientContext->getClientId(), $this->filters($request));
        $filename = \sprintf('catalogue-%s-%s.csv', (new AsciiSlugger())->slug($this->clientContext->getClientName())->lower(), date('Y-m-d'));

        $response = new StreamedResponse(static function () use ($articles): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Libellé', 'Catégorie', 'Type', 'Unité', 'Prix HT', 'TVA %', 'Stock'], ';', '"', '');
            foreach ($articles as $a) {
                fputcsv($out, [
                    $a['code'],
                    $a['libelle'],
                    $a['categorie'] ?? '',
                    $a['type_label'],
                    $a['unite_label'] ?? '',
                    number_format($a['prix_ht'], 2, ',', ''),
                    null !== $a['taux_tva'] ? number_format($a['taux_tva'], 2, ',', '') : '',
                    $a['suivi_stock'] ? number_format($a['stock_actuel'], 3, ',', '') : '',
                ], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', $filename));

        return $response;
    }

    /**
     * @return array{q: string, categorie: ?int, stock: string, tri: string}
     */
    private function filters(Request $request): array
    {
        $categorie = $request->query->get('categorie');
        $sort = $request->query->getString('tri');

        return [
            'q' => trim($request->query->getString('q')),
            'categorie' => \is_string($categorie) && ctype_digit($categorie) && (int) $categorie > 0 ? (int) $categorie : null,
            'stock' => $request->query->getString('stock'),
            'tri' => isset(self::SORTS[$sort]) ? $sort : 'categorie',
        ];
    }
}
