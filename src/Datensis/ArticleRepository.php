<?php

namespace App\Datensis;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Articles du catalogue (myfacturation_article) d'une société.
 */
final class ArticleRepository
{
    public const UNITS = [
        'u' => 'Unité', 'kg' => 'Kilogramme', 'g' => 'Gramme', 't' => 'Tonne', 'l' => 'Litre',
        'ml' => 'Millilitre', 'm' => 'Mètre', 'cm' => 'Centimètre', 'km' => 'Kilomètre',
        'm2' => 'Mètre carré', 'ha' => 'Hectare', 'm3' => 'Mètre cube', 'h' => 'Heure',
        'jour' => 'Jour', 'forfait' => 'Forfait', 'lot' => 'Lot', 'colis' => 'Colis', 'paquet' => 'Paquet',
    ];

    public const TYPES = [
        'vente' => 'Article à la vente',
        'prestation' => 'Prestation de service',
        'annexe' => 'Frais annexes',
    ];

    private const BOOLEAN_TYPES = ['suivi_stock' => ParameterType::BOOLEAN, 'actif' => ParameterType::BOOLEAN];

    public function __construct(
        private readonly Connection $datensisConnection,
        private readonly DatensisSchema $schema,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->schema->hasTables('myfacturation_article', 'myfacturation_categoriearticle');
    }

    /**
     * @param array{q?: ?string, categorie?: ?int, stock?: ?string, tri?: ?string, actifs?: bool} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function findByClient(int $clientId, array $filters = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $qb = $this->baseQuery()
            ->where('a.client_id = :client')
            ->setParameter('client', $clientId)
            ->orderBy('a.code_article');

        if ($filters['actifs'] ?? true) {
            $qb->andWhere('a.actif = :actif')->setParameter('actif', true, ParameterType::BOOLEAN);
        }

        if ('' !== $search = mb_strtolower(trim((string) ($filters['q'] ?? '')))) {
            $qb->andWhere('LOWER(a.libelle) LIKE :q OR LOWER(a.code_article) LIKE :q OR LOWER(a.description) LIKE :q')
                ->setParameter('q', '%'.addcslashes($search, '%_').'%');
        }

        if (null !== ($filters['categorie'] ?? null)) {
            $qb->andWhere('a.categorie_id = :categorie')->setParameter('categorie', $filters['categorie']);
        }

        $articles = array_map($this->hydrate(...), $qb->fetchAllAssociative());
        $stock = (string) ($filters['stock'] ?? '');
        if ('NON_SUIVI' === $stock) {
            $articles = array_values(array_filter($articles, static fn (array $a) => !$a['suivi_stock']));
        } elseif ($level = StockLevel::tryFrom($stock)) {
            $articles = array_values(array_filter($articles, static fn (array $a) => $a['suivi_stock'] && $a['stock_level'] === $level));
        }

        $sort = (string) ($filters['tri'] ?? 'categorie');
        usort($articles, static function (array $a, array $b) use ($sort): int {
            $name = static fn (array $article): string => mb_strtolower($article['libelle']);

            return match ($sort) {
                'libelle' => strnatcasecmp($a['libelle'], $b['libelle']),
                'code' => strnatcasecmp($a['code'], $b['code']),
                'prix' => $a['prix_ht'] <=> $b['prix_ht'] ?: strnatcasecmp($a['libelle'], $b['libelle']),
                'prix_desc' => $b['prix_ht'] <=> $a['prix_ht'] ?: strnatcasecmp($a['libelle'], $b['libelle']),
                'stock' => self::stockRank($a) <=> self::stockRank($b) ?: strnatcasecmp($a['libelle'], $b['libelle']),
                default => [mb_strtolower((string) ($a['categorie'] ?? 'zzz')), $name($a)] <=> [mb_strtolower((string) ($b['categorie'] ?? 'zzz')), $name($b)],
            };
        });

        return $articles;
    }

    /**
     * @param array<string, mixed> $article
     */
    private static function stockRank(array $article): int
    {
        if (!$article['suivi_stock'] || !$article['stock_level'] instanceof StockLevel) {
            return 4;
        }

        return match ($article['stock_level']) {
            StockLevel::Rupture => 0,
            StockLevel::Critique => 1,
            StockLevel::Faible => 2,
            StockLevel::Normal => 3,
        };
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
            ->where('a.client_id = :client AND a.id = :id')
            ->setParameter('client', $clientId)
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, string> nom de chaque catégorie, indexé par id
     */
    public function categories(int $clientId): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return array_map('strval', $this->datensisConnection->fetchAllKeyValue(
            'SELECT id, nom FROM myfacturation_categoriearticle WHERE client_id = ? ORDER BY nom',
            [$clientId],
        ));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \DomainException si le code ou le libellé existe déjà pour la société
     */
    public function create(int $clientId, array $data): int
    {
        $values = $this->toColumns($data);
        $values['code_article'] = '' !== $values['code_article'] ? $values['code_article'] : $this->nextCode($clientId);

        $this->guardUnique(fn () => $this->datensisConnection->insert('myfacturation_article', $values + [
            'client_id' => $clientId,
            'stock_actuel' => '0.000',
            'actif' => true,
            'date_creation' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP'),
        ], self::BOOLEAN_TYPES));

        return (int) $this->datensisConnection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \DomainException si le code ou le libellé existe déjà pour la société
     */
    public function update(int $clientId, int $id, array $data): void
    {
        $values = $this->toColumns($data);
        if ('' === $values['code_article']) {
            unset($values['code_article']);
        }

        $this->guardUnique(fn () => $this->datensisConnection->update(
            'myfacturation_article',
            $values,
            ['id' => $id, 'client_id' => $clientId],
            self::BOOLEAN_TYPES,
        ));
    }

    public function setActive(int $clientId, int $id, bool $active): void
    {
        $this->datensisConnection->update(
            'myfacturation_article',
            ['actif' => $active],
            ['id' => $id, 'client_id' => $clientId],
            self::BOOLEAN_TYPES,
        );
    }

    private function baseQuery(): QueryBuilder
    {
        return $this->datensisConnection->createQueryBuilder()
            ->select(
                'a.id', 'a.code_article', 'a.libelle', 'a.description', 'a.unite', 'a.prix_ht', 'a.taux_tva',
                'a.type_article', 'a.prix_achat', 'a.suivi_stock', 'a.stock_actuel', 'a.stock_minimum',
                'a.stock_critique', 'a.actif', 'a.categorie_id', 'c.nom AS categorie_nom',
            )
            ->from('myfacturation_article', 'a')
            ->leftJoin('a', 'myfacturation_categoriearticle', 'c', 'c.id = a.categorie_id');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $tracked = self::asBool($row['suivi_stock']);
        $stock = (float) $row['stock_actuel'];
        $minimum = null !== $row['stock_minimum'] ? (float) $row['stock_minimum'] : null;
        $critique = null !== $row['stock_critique'] ? (float) $row['stock_critique'] : null;

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code_article'],
            'libelle' => (string) $row['libelle'],
            'description' => (string) $row['description'],
            'categorie_id' => null !== $row['categorie_id'] ? (int) $row['categorie_id'] : null,
            'categorie' => $row['categorie_nom'],
            'unite' => $row['unite'],
            'unite_label' => self::UNITS[$row['unite']] ?? $row['unite'],
            'prix_ht' => (float) $row['prix_ht'],
            'taux_tva' => null !== $row['taux_tva'] ? (float) $row['taux_tva'] : null,
            'type' => $row['type_article'],
            'type_label' => self::TYPES[$row['type_article']] ?? $row['type_article'],
            'prix_achat' => null !== $row['prix_achat'] ? (float) $row['prix_achat'] : null,
            'suivi_stock' => $tracked,
            'stock_actuel' => $stock,
            'stock_minimum' => $minimum,
            'stock_critique' => $critique,
            'stock_level' => $tracked ? StockLevel::compute($stock, $minimum, $critique) : null,
            'actif' => self::asBool($row['actif']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function toColumns(array $data): array
    {
        $decimal = static fn (mixed $v, int $scale) => null === $v || '' === $v ? null : number_format((float) $v, $scale, '.', '');

        return [
            'code_article' => trim((string) ($data['code_article'] ?? '')),
            'libelle' => trim((string) $data['libelle']),
            'description' => '' !== trim((string) ($data['description'] ?? '')) ? trim((string) $data['description']) : null,
            'categorie_id' => $data['categorie_id'] ?? null,
            'unite' => $data['unite'] ?: null,
            'prix_ht' => $decimal($data['prix_ht'] ?? 0, 6) ?? '0.000000',
            'taux_tva' => $decimal($data['taux_tva'] ?? null, 2),
            'type_article' => $data['type_article'] ?? 'vente',
            'prix_achat' => $decimal($data['prix_achat'] ?? null, 2),
            'suivi_stock' => (bool) ($data['suivi_stock'] ?? false),
            'stock_actuel' => $decimal($data['stock_actuel'] ?? 0, 3) ?? '0.000',
            'stock_minimum' => $decimal($data['stock_minimum'] ?? null, 3),
            'stock_critique' => $decimal($data['stock_critique'] ?? null, 3),
        ];
    }

    /**
     * Même format que Article.generer_code_article() : ART + 9 chiffres, séquence propre à la société.
     */
    private function nextCode(int $clientId): string
    {
        $codes = $this->datensisConnection->fetchFirstColumn(
            "SELECT code_article FROM myfacturation_article WHERE client_id = ? AND code_article LIKE 'ART%'",
            [$clientId],
        );

        $max = 0;
        foreach ($codes as $code) {
            $suffix = substr((string) $code, 3);
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return 'ART'.str_pad((string) ($max + 1), 9, '0', \STR_PAD_LEFT);
    }

    private static function asBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function guardUnique(callable $write): void
    {
        try {
            $write();
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('Un article avec ce code ou ce libellé existe déjà pour cette société.');
        }
    }
}
