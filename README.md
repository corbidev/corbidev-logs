# Corbidev Logs - Guide Consommateur

Ce document est le point d'entree pour utiliser la plateforme.

## Objectif

Corbidev Logs permet:

- d'ingester des logs via API HTTP
- de traiter les logs en pipeline robuste
- de consulter les logs via dashboard web

## Prerequis

- PHP 8.4+
- Composer
- Docker (recommande)

## Installation rapide

```bash
cd logs
composer install --no-interaction --prefer-dist
cp ../env.symfony.example .env.local
docker compose up -d
```

Alternative PowerShell:

```powershell
Copy-Item ..\env.symfony.example .env.local -Force
```

## Utilisation API

Endpoint:

- `POST /api/logs`

Headers obligatoires:

- `Content-Type: application/json`
- `Authorization: Bearer <token_opaque>`

Exemple minimal:

```json
{
  "logs": [
    {
      "message": "Erreur paiement",
      "level": "error",
      "domain": "billing",
      "env": "prod",
      "httpStatus": 500,
      "client": "api"
    }
  ]
}
```

## Dashboard

Routes:

- `GET /dashboard/logs`
- `GET /dashboard/logs/{externalId}`

## Tests

```bash
cd logs
php bin/phpunit
```

## Documentation complete

Toute la documentation technique est centralisee ici:

- [docs/README.md](docs/README.md)
