-- Base privee e-client : la charte graphique ne depend pas de DATENSIS.
-- client_id correspond a l'identifiant de la societe DATENSIS mais n'est pas une FK entre bases.
CREATE TABLE IF NOT EXISTS eclient_charte (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL UNIQUE,
    nom_entreprise VARCHAR(255) NOT NULL DEFAULT '',
    couleur_principale VARCHAR(7) NOT NULL DEFAULT '#667eea',
    couleur_secondaire VARCHAR(7) NOT NULL DEFAULT '#764ba2',
    couleur_accent VARCHAR(7) NOT NULL DEFAULT '#ee6c4d',
    couleur_fond VARCHAR(7) NOT NULL DEFAULT '#f3f4f6',
    couleur_navbar VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    couleur_carte VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    logo VARCHAR(255) DEFAULT NULL,
    logo_url VARCHAR(500) DEFAULT NULL,
    date_creation TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
