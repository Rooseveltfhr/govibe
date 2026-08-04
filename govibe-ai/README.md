# GOVIBE AI

Plateforme SaaS IA pour Haïti et la Caraïbe — passerelle multi-fournisseurs,
suite d'applications IA et moteur d'agents sectoriels.

- **Architecture** : [`../docs/govibe-ai/ARCHITECTURE.md`](../docs/govibe-ai/ARCHITECTURE.md)
- **Décisions** : [`../docs/govibe-ai/adr/`](../docs/govibe-ai/adr/)

## Stack

Laravel 13 · PHP 8.4 · PostgreSQL 16 + pgvector · Redis · modules `nwidart/laravel-modules`
· Pest · PHPStan (larastan) · Pint · Docker.

## Démarrage (Docker)

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
# → http://localhost:8000  (Mailpit: :8025, MinIO console: :9001)
```

## Démarrage (local sans Docker)

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate        # SQLite par défaut
php artisan serve
```

## Qualité

```bash
vendor/bin/pint            # style
vendor/bin/phpstan analyse # analyse statique
vendor/bin/pest            # tests
```

## i18n

4 langues : `fr` (source), `ht`, `en`, `es` — fichiers `lang/*.json`.
Négociation : `?lang=` → session → cookie → `Accept-Language` → `fr`.
Tester : `http://localhost:8000/?lang=ht`.

## Modules

Un module par domaine dans `Modules/` (voir le document d'architecture, §2).
Créer un module : `php artisan module:make <Nom>`.
