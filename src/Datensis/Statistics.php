<?php

namespace App\Datensis;

/**
 * Indicateurs calculés à partir des articles et des factures d'une société.
 */
final class Statistics
{
    private const OUTSTANDING = [InvoiceStatus::Validee, InvoiceStatus::Envoyee, InvoiceStatus::PartiellementPayee, InvoiceStatus::Impayee];

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forClient(int $clientId): array
    {
        $articles = $this->articleRepository->findByClient($clientId);
        $invoices = $this->invoiceRepository->findByClient($clientId);
        $billed = array_filter($invoices, static fn (array $i) => $i['statut']->isBilled());

        $byCategory = [];
        $stockLevels = array_fill_keys(array_map(static fn (StockLevel $l) => $l->label(), StockLevel::cases()), 0);
        foreach ($articles as $article) {
            $category = $article['categorie'] ?? 'Sans catégorie';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            if ($article['stock_level']) {
                ++$stockLevels[$article['stock_level']->label()];
            }
        }
        arsort($byCategory);

        $byStatus = [];
        foreach ($invoices as $invoice) {
            $status = $invoice['statut'];
            $byStatus[$status->value] ??= ['label' => $status->label(), 'color' => $status->chartColor(), 'count' => 0];
            ++$byStatus[$status->value]['count'];
        }

        $year = (int) date('Y');
        $months = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        for ($i = 11; $i >= 0; --$i) {
            $months[$cursor->modify("-$i months")->format('Y-m')] = 0.0;
        }

        $revenueYear = 0.0;
        $outstanding = 0.0;
        foreach ($billed as $invoice) {
            $date = $invoice['date_emission'];
            if ($date && isset($months[$date->format('Y-m')])) {
                $months[$date->format('Y-m')] += $invoice['montant_ht'];
            }
            if ($date && (int) $date->format('Y') === $year) {
                $revenueYear += $invoice['montant_ht'];
            }
            if (\in_array($invoice['statut'], self::OUTSTANDING, true)) {
                $outstanding += max(0.0, $invoice['montant_ttc'] - $invoice['montant_paye']);
            }
        }

        return [
            'articles_available' => $this->articleRepository->isAvailable(),
            'invoices_available' => $this->invoiceRepository->isAvailable(),
            'article_count' => \count($articles),
            'tracked_count' => \count(array_filter($articles, static fn (array $a) => $a['suivi_stock'])),
            'stock_alerts' => \count(array_filter($articles, static fn (array $a) => $a['stock_level']?->isAlert())),
            'invoice_count' => \count($billed),
            'revenue_year' => $revenueYear,
            'outstanding' => $outstanding,
            'year' => $year,
            'by_category' => ['labels' => array_keys($byCategory), 'values' => array_values($byCategory)],
            'stock_levels' => ['labels' => array_keys($stockLevels), 'values' => array_values($stockLevels)],
            'by_status' => [
                'labels' => array_column($byStatus, 'label'),
                'values' => array_column($byStatus, 'count'),
                'colors' => array_column($byStatus, 'color'),
            ],
            'monthly_revenue' => [
                'labels' => array_map(static fn (string $m) => substr($m, 5, 2).'/'.substr($m, 0, 4), array_keys($months)),
                'values' => array_map(static fn (float $v) => round($v, 2), array_values($months)),
            ],
            'recent_invoices' => \array_slice(array_values($billed), 0, 5),
        ];
    }
}
