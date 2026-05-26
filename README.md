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

Exemple complet (batch de 2 logs):

```json
{
  "logs": [
    {
      "message": "Erreur sur endpoint /auth/users",
      "level": "error",
      "domain": "auth",
      "env": "dev",
      "httpStatus": 500,
      "client": "api",
      "requestId": "req-20260526-12345",
      "externalId": "9d4f3f1c-0a77-4c85-9f43-cbc2fda9b1ab",
      "clientDate": "2026-05-26T21:42:00+02:00",
      "request": {
        "method": "GET",
        "uri": "/auth/users",
        "route": "api_auth_users",
        "headers": {
          "x-trace-id": "trace-12345",
          "x-forwarded-for": "127.0.0.1"
        }
      },
      "context": {
        "traceId": "trace-12345",
        "userId": 42,
        "durationMs": 231,
        "service": "auth-service"
      },
      "extra": {
        "release": "1.3.4",
        "node": "srv-auth-01"
      },
      "tags": [
        "backend",
        "exception",
        "auth"
      ],
      "exception": {
        "class": "RuntimeException",
        "message": "Database timeout",
        "code": 0,
        "file": "/var/www/src/Auth/UserProvider.php",
        "line": 87,
        "trace": [
          "Auth\\UserProvider->load()",
          "Auth\\Controller\\UserController->__invoke()"
        ]
      },
      "server": {
        "hostname": "api-01",
        "ip": "10.0.0.12"
      },
      "runtime": {
        "php": "8.4",
        "memoryMb": 64
      },
      "user": {
        "id": 42,
        "role": "admin"
      }
    },
    {
      "message": "Paiement refuse",
      "level": "warning",
      "domain": "billing",
      "env": "prod",
      "httpStatus": 402,
      "client": "web",
      "request": {
        "method": "POST",
        "uri": "/payments/987654",
        "route": "api_payments_create"
      },
      "context": {
        "orderId": "ORD-2026-0001",
        "attempt": 2
      },
      "tags": [
        "billing",
        "payment"
      ]
    }
  ]
}
```

## Traitement queue (persistance SQL)

Le endpoint `POST /api/logs` repond `202 Accepted` quand le log est place en queue.
La persistence en base est faite ensuite par la commande queue consumer.

Commande manuelle:

```bash
cd logs
php bin/console app:queue:process
```

Commande manuelle avec limite:

```bash
cd logs
php bin/console app:queue:process 200
```

Exemple cron (Linux) - toutes les minutes:

```cron
* * * * * cd /var/www/corbidev-logs/logs && php bin/console app:queue:process 200 >> var/log/queue-cron.log 2>&1
```

Exemple cron avec verrou (evite les executions concurrentes):

```cron
* * * * * cd /var/www/corbidev-logs/logs && flock -n /tmp/corbidev-queue.lock php bin/console app:queue:process 200 >> var/log/queue-cron.log 2>&1
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
