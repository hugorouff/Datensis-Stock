<?php

declare(strict_types=1);

namespace App\Datensis;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Charte graphique propre a e-client.
 * Connexion Doctrine "default" : SQLite en local, PostgreSQL sur Scalingo.
 * Les articles et les factures DATENSIS n'utilisent pas cette table.
 */
final class BrandingRepository
{
    private const TABLE = 'eclient_charte';

    /** @var array<int, Branding> */
    private array $cache = [];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $eclientConnection,
        // Conserve pour les autres appels existants a mediaUrl().
        private readonly string $datensisMediaUrl,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function isAvailable(): bool
    {
        // Ne masque pas une erreur de connexion en la faisant passer pour une table absente.
        return $this->eclientConnection->createSchemaManager()->tablesExist([self::TABLE]);
    }

    public function forClient(int $clientId, string $companyName): Branding
    {
        if (isset($this->cache[$clientId])) {
            return $this->cache[$clientId];
        }

        $row = $this->isAvailable()
            ? $this->eclientConnection->fetchAssociative(
                'SELECT * FROM '.self::TABLE.' WHERE client_id = :client_id',
                ['client_id' => $clientId],
            )
            : false;

        if (false === $row) {
            return $this->cache[$clientId] = new Branding(companyName: $companyName);
        }

        return $this->cache[$clientId] = new Branding(
            primaryColor: Branding::normalizeColor($row['couleur_principale'] ?? null, Branding::DEFAULT_PRIMARY),
            secondaryColor: Branding::normalizeColor($row['couleur_secondaire'] ?? null, Branding::DEFAULT_SECONDARY),
            accentColor: Branding::normalizeColor($row['couleur_accent'] ?? null, Branding::DEFAULT_ACCENT),
            backgroundColor: Branding::normalizeColor($row['couleur_fond'] ?? null, Branding::DEFAULT_BACKGROUND),
            navbarColor: Branding::normalizeColor($row['couleur_navbar'] ?? null, Branding::DEFAULT_NAVBAR),
            cardColor: Branding::normalizeColor($row['couleur_carte'] ?? null, Branding::DEFAULT_CARD),
            logoUrl: $this->resolveLogoUrl($row['logo'] ?? null, $row['logo_url'] ?? null, $clientId),
            externalLogoUrl: ($row['logo_url'] ?? null) ?: null,
            companyName: $companyName,
            persisted: true,
        );
    }

    /**
     * Cree ou met a jour la charte d'un client.
     * Signature identique a celle utilisee par CharteController.
     */
    public function save(
        int $clientId,
        string $companyName,
        string $primaryColor,
        string $secondaryColor,
        ?string $logoUrl,
        string $accentColor = Branding::DEFAULT_ACCENT,
        string $backgroundColor = Branding::DEFAULT_BACKGROUND,
        string $navbarColor = Branding::DEFAULT_NAVBAR,
        string $cardColor = Branding::DEFAULT_CARD,
        ?string $logoPath = null,
        bool $removeLogo = false,
    ): void {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');

        // On conserve le logo deja enregistre lorsqu'aucun nouveau fichier n'est fourni.
        // Contrairement a une condition liee a un parametre SQL, la clause est valide
        // de la meme facon dans SQLite et PostgreSQL.
        $logoUpdate = $removeLogo
            ? 'logo = NULL,'
            : (null !== $logoPath ? 'logo = excluded.logo,' : '');

        $sql = <<<SQL
INSERT INTO eclient_charte (
    client_id, nom_entreprise,
    couleur_principale, couleur_secondaire, couleur_accent,
    couleur_fond, couleur_navbar, couleur_carte,
    logo, logo_url, date_creation, date_modification
) VALUES (
    :client_id, :company_name,
    :primary_color, :secondary_color, :accent_color,
    :background_color, :navbar_color, :card_color,
    :logo, :logo_url, :created_at, :updated_at
)
ON CONFLICT (client_id) DO UPDATE SET
    nom_entreprise = excluded.nom_entreprise,
    couleur_principale = excluded.couleur_principale,
    couleur_secondaire = excluded.couleur_secondaire,
    couleur_accent = excluded.couleur_accent,
    couleur_fond = excluded.couleur_fond,
    couleur_navbar = excluded.couleur_navbar,
    couleur_carte = excluded.couleur_carte,
    logo_url = excluded.logo_url,
    {$logoUpdate}
    date_modification = excluded.date_modification
SQL;

        $this->eclientConnection->executeStatement($sql, [
            'client_id' => $clientId,
            'company_name' => $companyName,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'accent_color' => $accentColor,
            'background_color' => $backgroundColor,
            'navbar_color' => $navbarColor,
            'card_color' => $cardColor,
            'logo' => $removeLogo ? null : $logoPath,
            'logo_url' => $removeLogo ? null : ($logoUrl ?: null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        unset($this->cache[$clientId]);
    }

    /**
     * Compatibilite : autres ressources liees a DATENSIS pouvant utiliser mediaUrl().
     * Cette methode n'est PAS utilisee pour retrouver un logo de charte e-client.
     */
    public function mediaUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ('' === $path) {
            return null;
        }
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return rtrim($this->datensisMediaUrl, '/').'/'.ltrim($path, '/');
    }

    private function resolveLogoUrl(?string $logo, ?string $logoUrl, int $clientId): ?string
    {
        $logo = str_replace('\\', '/', trim((string) $logo));
        // Uniquement les logos de ce client et stockes dans le portail e-client.
        if (preg_match('~^charte/'.preg_quote((string) $clientId, '~').'/logo[.-][a-zA-Z0-9._-]+$~', $logo)) {
            $fullPath = $this->projectDir.'/public/uploads/'.$logo;
            if (is_file($fullPath)) {
                return '/uploads/'.$logo;
            }
        }

        $logoUrl = trim((string) $logoUrl);
        return preg_match('~^https?://~i', $logoUrl) ? $logoUrl : null;
    }
}
