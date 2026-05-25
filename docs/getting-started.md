# Demarrage rapide

## Prerequis

- PHP 8.4+
- Composer
- Docker (recommande)

## Installation

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

## Verification

```bash
cd logs
php bin/phpunit
```

## Etape suivante

Lire [operations.md](operations.md) pour l'exploitation queue/persistence.
