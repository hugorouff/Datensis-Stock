<?php

namespace App\Datensis;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Factures émises depuis Datensis (myfacturation_facturedirecte) par une société.
 */
final class InvoiceRepository
{
    public function __construct(
        private readonly Connection $datensisConnection,
        private readonly DatensisSchema $schema,
        private readonly BrandingRepository $brandingRepository,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->schema->hasTables('myfacturation_facturedirecte', 'myfacturation_lignefacturedirecte', 'ventes_customer');
    }

    /**
     * @param array{q?: ?string, statut?: ?string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function findByClient(int $clientId, array $filters = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $qb = $this->baseQuery()
            ->where('f.client_id = :client')
            ->setParameter('client', $clientId)
            ->orderBy('CASE WHEN f.date_emission IS NULL THEN 1 ELSE 0 END')
            ->addOrderBy('f.date_emission', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ('' !== $search = mb_strtolower(trim((string) ($filters['q'] ?? '')))) {
            $qb->andWhere('LOWER(f.numero) LIKE :q OR LOWER(cu.nom_customer) LIKE :q OR LOWER(f.objet) LIKE :q')
                ->setParameter('q', '%'.addcslashes($search, '%_').'%');
        }

        if ($status = InvoiceStatus::tryFrom((string) ($filters['statut'] ?? ''))) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $status->value);
        }

        return array_map($this->hydrate(...), $qb->fetchAllAssociative());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $clientId, int $id): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $row = $this->baseQuery()
            ->where('f.client_id = :client AND f.id = :id')
            ->setParameter('client', $clientId)
            ->setParameter('id', $id)
            ->fetchAssociative();

        if (false === $row) {
            return null;
        }

        $invoice = $this->hydrate($row);
        $invoice['lignes'] = array_map($this->hydrateLine(...), $this->datensisConnection->fetchAllAssociative(
            'SELECT l.libelle_affichage, l.description, l.quantite, l.unite, l.prix_unitaire_ht, l.taux_tva,
                    l.montant_ht, l.montant_tva, l.montant_ttc, a.libelle AS article_libelle, a.code_article
             FROM myfacturation_lignefacturedirecte l
             LEFT JOIN myfacturation_article a ON a.id = l.article_id
             WHERE l.facture_directe_id = ?
             ORDER BY l.ordre, l.id',
            [$id],
        ));

        return $invoice;
    }

    /**
     * Libellé résolu comme LigneFactureDirecte.libelle_effectif dans Datensis.
     *
     * @param array<string, mixed> $line
     *
     * @return array<string, mixed>
     */
    private function hydrateLine(array $line): array
    {
        $description = trim((string) $line['description']);
        $title = trim((string) $line['libelle_affichage']) ?: trim(strip_tags((string) $line['article_libelle']));

        return [
            'libelle' => $title ?: $description ?: 'Prestation',
            'description' => '' !== $title ? $description : '',
            'code' => $line['code_article'],
            'quantite' => (float) $line['quantite'],
            'unite' => $line['unite'],
            'prix_unitaire_ht' => (float) $line['prix_unitaire_ht'],
            'taux_tva' => (float) $line['taux_tva'],
            'montant_ht' => (float) $line['montant_ht'],
            'montant_tva' => (float) $line['montant_tva'],
            'montant_ttc' => (float) $line['montant_ttc'],
        ];
    }

    private function baseQuery(): QueryBuilder
    {
        return $this->datensisConnection->createQueryBuilder()
            ->select(
                'f.id', 'f.numero', 'f.date_emission', 'f.date_echeance', 'f.date_paiement', 'f.objet', 'f.statut',
                'f.montant_ht', 'f.montant_tva', 'f.montant_ttc', 'f.montant_paye', 'f.pdf_facture',
                'cu.nom_customer',
            )
            ->from('myfacturation_facturedirecte', 'f')
            ->leftJoin('f', 'ventes_customer', 'cu', 'cu.id = f.customer_id');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $date = static fn (?string $v) => $v ? new \DateTimeImmutable(substr($v, 0, 10)) : null;

        return [
            'id' => (int) $row['id'],
            'numero' => $row['numero'] ?: 'BR-'.$row['id'],
            'date_emission' => $date($row['date_emission']),
            'date_echeance' => $date($row['date_echeance']),
            'date_paiement' => $date($row['date_paiement']),
            'objet' => (string) $row['objet'],
            'statut' => InvoiceStatus::tryFrom((string) $row['statut']) ?? InvoiceStatus::Brouillon,
            'montant_ht' => (float) $row['montant_ht'],
            'montant_tva' => (float) $row['montant_tva'],
            'montant_ttc' => (float) $row['montant_ttc'],
            'montant_paye' => (float) $row['montant_paye'],
            'customer' => $row['nom_customer'] ?: 'Client non renseigné',
            'pdf_url' => $this->brandingRepository->mediaUrl($row['pdf_facture']),
        ];
    }
}
