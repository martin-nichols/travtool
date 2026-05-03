# Travtool

Base de projet pour un outil Travian avec deux surfaces principales :

- `app/` : application web Laravel + Inertia + Vue 3
- `extension/` : extension navigateur WXT + Vue + TypeScript
- `docs/` : décisions d'architecture et état du bootstrap

## Etat actuel

- Le socle web officiel Laravel + Vue a été injecté dans `app/`
- Les dépendances Node du web sont installées
- Le socle WXT + Vue + TypeScript a été créé dans `extension/`
- Les dépendances Node de l'extension sont installées
- Le build de l'extension fonctionne

## Blocage actuel

La partie PHP n'est pas encore installée dans `app/` car `Composer` échoue sur cette machine à cause d'un problème local de certificats TLS avec la stack PHP/XAMPP.

Conséquences :

- `app/vendor/` n'existe pas encore
- les commandes `php artisan ...` ne sont pas encore exécutables
- le build front du starter Laravel échoue tant que les dépendances Composer ne sont pas installées

Voir [docs/bootstrap-status.md](docs/bootstrap-status.md) pour le détail.

## Structure

```text
travtool/
  app/
  docs/
  extension/
  tools/   # artefacts locaux de bootstrap, ignorés par git
```
