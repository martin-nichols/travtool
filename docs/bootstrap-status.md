# Bootstrap Status

## Ce qui a ete pose

### Web

- Base officielle : `laravel/blank-vue-starter-kit`
- Stack front : Inertia + Vue 3 + TypeScript
- Dependances Node installees dans `app/`

### Extension

- Base officielle : `WXT` template `vue`
- TypeScript actif par defaut
- Dependances Node installees dans `extension/`
- Build de verification valide

## Ce qui bloque encore

La machine dispose de PHP via XAMPP, mais :

- `php` n'est pas dans le `PATH`
- `composer` n'etait pas installe globalement
- la pile TLS de PHP/Composer echoue sur les requetes HTTPS vers Packagist/GitHub

Concretement, cela empeche pour l'instant :

- `composer install` dans `app/`
- l'installation de `vendor/`
- l'execution normale de `php artisan`

## Impact pratique

Le dossier `app/` est structurellement en place, mais il n'est pas encore runnable cote Laravel.

Le dossier `extension/` est en etat de marche cote Node/WXT.

## Prochaine etape recommande

1. Corriger l'environnement PHP/Composer local.
2. Installer les dependances Composer dans `app/`.
3. Configurer `.env` pour MariaDB.
4. Ajouter Sanctum et l'infrastructure de base Laravel du projet.
5. Figer ensuite le modele SQL V1 et la pipeline `map.sql`.

## Verification deja faite

- `extension`: `npm run build` OK
- `app`: `npm run build` KO tant que `vendor/` et `php artisan` manquent
