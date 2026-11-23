# Datensis Stock

Portail Symfony issu d’E-Client, dédié aux articles, au stock et à la charte graphique des sociétés liées à Datensis Expert.

## Fonctionnalités

- Consulter le catalogue et rechercher les articles associés à la société active.
- Gérer les articles et suivre les niveaux de stock, avec alertes selon les seuils disponibles.
- Consulter les factures issues de Datensis et les statistiques du portail.
- Personnaliser le logo et les couleurs par société. La palette DATENSIS sert de valeur par défaut.
- Choisir le thème clair, sombre, automatique ou original depuis l’interface.

## Données et compte

| Connexion | Usage |
|---|---|
| `DATABASE_URL` | Base propre au portail, notamment la charte graphique. |
| `DATENSIS_DATABASE_URL` | Base Django de Datensis Expert, consultée via Doctrine DBAL. Elle fournit les comptes, les sociétés, les articles et les données de facturation. |
| `DATENSIS_MEDIA_URL` | Adresse des médias si nécessaire pour les ressources servies par Datensis. |

Le formulaire attend le **nom d’utilisateur Django** (`auth_user.username`), avec son mot de passe Datensis. Le compte doit être actif et rattaché à au moins une société. Pour un environnement local, la base Datensis peut être une copie SQLite compatible. En production, renseigner les URL PostgreSQL et les droits nécessaires à chaque action sur les articles. Les migrations Symfony concernent la base du portail.

## Installation locale

Prérequis de l’archive : PHP 8.2+ selon Composer, Composer, extensions requises par le verrou (dont `pdo_pgsql`), et `pdo_sqlite` si SQLite est utilisé. Renseigner `APP_SECRET`, les deux URL de base et `DEFAULT_URI` dans l’environnement local.

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public
```

En production, compiler les ressources avec `php bin/console asset-map:compile` et servir depuis `public/`. Vérifier la connexion, le changement de société, le catalogue et la persistance de la charte.
