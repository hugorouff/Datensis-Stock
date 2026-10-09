<?php

namespace App\Datensis;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BrandingRepository
{
    private const TABLE = 'myfacturation_documentpersonnalisation';

    /** @var array<int, Branding> */
    private array $cache = [];

    public function __construct(
        private readonly Connection $datensisConnection,
        private readonly DatensisSchema $schema,
        private readonly string $datensisMediaUrl,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->schema->hasTable(self::TABLE);
    }

    public function forClient(int $clientId, string $companyName): Branding
    {
        if (isset($this->cache[$clientId])) {
            return $this->cache[$clientId];
        }

        $row = $this->isAvailable()
            ? $this->datensisConnection->fetchAssociative('SELECT * FROM '.self::TABLE.' WHERE client_id = ?', [$clientId])
            : false;

        if (false === $row) {
            return $this->cache[$clientId] = new Branding(companyName: $companyName);
        }

        return $this->cache[$clientId] = new Branding(
            primaryColor: Branding::normalizeColor($row['couleur_principale'], Branding::DEFAULT_PRIMARY),
            secondaryColor: Branding::normalizeColor($row['couleur_secondaire'], Branding::DEFAULT_SECONDARY),
            accentColor: Branding::normalizeColor($row['couleur_accent'] ?? null, Branding::DEFAULT_ACCENT),
            backgroundColor: Branding::normalizeColor($row['couleur_fond'] ?? null, Branding::DEFAULT_BACKGROUND),
            navbarColor: Branding::normalizeColor($row['couleur_navbar'] ?? null, Branding::DEFAULT_NAVBAR),
            cardColor: Branding::normalizeColor($row['couleur_carte'] ?? null, Branding::DEFAULT_CARD),
            logoUrl: $this->resolveLogoUrl($row['logo'] ?? null, $row['logo_url'] ?? null),
            externalLogoUrl: $row['logo_url'] ?: null,
            companyName: (string) ($row['nom_entreprise'] ?: $companyName),
            address: (string) $row['adresse_entreprise'],
            phone: (string) $row['telephone_entreprise'],
            email: (string) $row['email_entreprise'],
            siret: (string) $row['siret_entreprise'],
            vatNumber: (string) $row['tva_entreprise'],
            persisted: true,
        );
    }

    /**
     * Met à jour la charte de la société ; elle s'applique à tous ses utilisateurs, ici comme dans Datensis.
     */
    public function save(int $clientId, string $companyName, string $primaryColor, string $secondaryColor, ?string $logoUrl, string $accentColor = Branding::DEFAULT_ACCENT, string $backgroundColor = Branding::DEFAULT_BACKGROUND, string $navbarColor = Branding::DEFAULT_NAVBAR, string $cardColor = Branding::DEFAULT_CARD, ?string $logoPath = null, bool $removeLogo = false): void
    {
        $this->ensureExtraColumns();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');
        $values = [
            'couleur_principale' => $primaryColor,
            'couleur_secondaire' => $secondaryColor,
            'couleur_accent' => $accentColor,
            'couleur_fond' => $backgroundColor,
            'couleur_navbar' => $navbarColor,
            'couleur_carte' => $cardColor,
            'logo_url' => $removeLogo ? null : ($logoUrl ?: null),
            'date_modification' => $now,
        ];
        if ($removeLogo) {
            $values['logo'] = null;
        } elseif (null !== $logoPath) {
            $values['logo'] = $logoPath;
        }

        $updated = $this->datensisConnection->update(self::TABLE, $values, ['client_id' => $clientId]);

        if (0 === $updated) {
            // Django ne pose pas les valeurs par défaut en base : on reprend celles du modèle DocumentPersonnalisation
            $this->datensisConnection->insert(self::TABLE, $values + [
                'client_id' => $clientId,
                'logo' => null,
                'nom_entreprise' => $companyName,
                'adresse_entreprise' => "456 Avenue des Affaires\n69000 Lyon, France",
                'telephone_entreprise' => '04 78 90 12 34',
                'email_entreprise' => 'contact@ml2c-conseil.com',
                'siret_entreprise' => '98765432100012',
                'tva_entreprise' => 'FR98765432101',
                'banque_nom' => '',
                'banque_iban' => '',
                'banque_bic' => '',
                'delai_paiement' => '',
                'conditions_paiement' => "Paiement à 30 jours fin de mois\nEn cas de retard de paiement, une pénalité de 3 fois le taux d'intérêt légal sera appliquée",
                'mentions_legales' => "Forme juridique : Société à responsabilité limitée (SARL)\nCapital social : 50 000,00 €\nRCS : Lyon B 987 654 321\nRégime fiscal : TVA applicable, art. 293B du CGI",
                'date_creation' => $now,
            ]);
        }

        unset($this->cache[$clientId]);
    }

    public function mediaUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ('' === $path) {
            return null;
        }

        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return rtrim($this->datensisMediaUrl, '/').'/'.ltrim($path, '/');
    }

    private function resolveLogoUrl(?string $logo, ?string $logoUrl): ?string
    {
        $logo = str_replace('\\', '/', trim((string) $logo));
        if (str_starts_with($logo, 'charte/') && is_file($this->projectDir.'/public/uploads/'.$logo)) {
            return '/uploads/'.$logo;
        }

        return $this->mediaUrl($logo) ?? $this->mediaUrl($logoUrl);
    }

    /**
     * Couleurs propres au portail : on les ajoute à la table Datensis si elles manquent.
     */
    private function ensureExtraColumns(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $columns = array_map(
            'strtolower',
            array_keys($this->datensisConnection->createSchemaManager()->listTableColumns(self::TABLE)),
        );
        if (!\in_array('couleur_accent', $columns, true)) {
            $this->datensisConnection->executeStatement('ALTER TABLE '.self::TABLE.' ADD COLUMN couleur_accent VARCHAR(7) DEFAULT NULL');
        }
        if (!\in_array('couleur_fond', $columns, true)) {
            $this->datensisConnection->executeStatement('ALTER TABLE '.self::TABLE.' ADD COLUMN couleur_fond VARCHAR(7) DEFAULT NULL');
        }
        if (!\in_array('couleur_navbar', $columns, true)) {
            $this->datensisConnection->executeStatement('ALTER TABLE '.self::TABLE.' ADD COLUMN couleur_navbar VARCHAR(7) DEFAULT NULL');
        }
        if (!\in_array('couleur_carte', $columns, true)) {
            $this->datensisConnection->executeStatement('ALTER TABLE '.self::TABLE.' ADD COLUMN couleur_carte VARCHAR(7) DEFAULT NULL');
        }
    }
}
