# Mutualized Logging Platform

Système de logs mutualisé robuste inspiré de GlitchTip/Sentry, conçu pour fonctionner sur hébergement mutualisé avec Symfony 8 et PHP 8.4.

---

# 🎯 Objectifs

Le projet vise à fournir une plateforme de logs :

- robuste
- simple
- prévisible
- maintenable
- compatible mutualisé
- optimisée pour les performances d’écriture

Le système est conçu pour :

- ne jamais casser à cause des données reçues
- absorber des pics de logs
- isoler les erreurs
- garantir un pipeline stable

---

# 🧠 Philosophie

## Priorités absolues

1. robustesse
2. simplicité
3. prédictibilité
4. maintenabilité
5. performances d’écriture

---

## Principes fondamentaux

- un log imparfait vaut mieux qu’un log perdu
- toutes les données externes sont hostiles
- aucun comportement implicite
- aucun composant magique
- flux simples uniquement
- architecture explicite
- responsabilités uniques

---

# ⚙️ Stack Technique

## Backend

- PHP 8.4
- Symfony 8
- Doctrine ORM
- MySQL / MariaDB
- Monolog

---

## Frontend

- Twig
- HTMX
- Alpine.js
- JavaScript vanilla

### Optionnel

- Tailwind CSS
- Vite

---

# 🏗️ Architecture

Le système est séparé en deux parties :

---

## WRITE SIDE

Responsable :

- ingestion
- validation minimale
- queue
- persistence

Priorité :

- écriture rapide
- aucune perte
- robustesse maximale

---

## READ SIDE

Responsable :

- dashboard
- recherche
- pagination
- filtres

Priorité :

- lecture simple
- requêtes bornées
- index ciblés

---

# 📂 Structure du projet

```txt
src/

- Ingestion/
- Log/
- Queue/
- Persistence/
- Search/
- Dashboard/
- Project/
- ApiToken/
- Shared/
```

---

# 🌐 Architecture HTTP

## API

Routes :

```txt
/api/*
```

Règles :

- JSON uniquement
- jamais de HTML

---

## Dashboard

Routes :

```txt
/dashboard/*
```

Règles :

- HTML uniquement
- jamais de JSON

---

# 🔐 Authentification

Le système utilise :

- des tokens opaques hashés
- révocables
- avec expiration

---

## Important

Le JWT est volontairement évité pour l’ingestion des logs.

Pourquoi :

- complexité inutile
- révocation difficile
- coût supplémentaire
- architecture non distribuée

---


---

# 📡 Exemple de payload attendu par l’API

## Endpoint

```txt
POST /api/logs
```

---

## Headers

```http
Authorization: Bearer lgp_xxxxxxxxx
Content-Type: application/json
```

---

## Payload JSON

```json
{
  "logs": [
    {
      "externalId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",

      "domain": "app.example.com",
      "uri": "/login",
      "method": "POST",
      "ip": "192.168.1.25",

      "message": "User login failed",
      "level": "ERROR",
      "env": "prod",

      "client": "web",
      "version": "1.0.0",

      "clientDate": "2026-05-08T14:12:32Z",

      "userId": 42,
      "httpStatus": 401,

      "context": {
        "query": {
          "redirect": "/admin"
        },

        "request": {
          "requestId": "req_8f5c1a",
          "userAgent": "Mozilla/5.0"
        },

        "security": {
          "firewall": "main",
          "authenticator": "LoginFormAuthenticator"
        },

        "debug": {
          "memoryMb": 12.4,
          "durationMs": 153
        }
      }
    }
  ]
}
```

---

## Réponse succès

```json
{
  "success": true,
  "data": {
    "received": 1
  }
}
```

---

## Réponse erreur

```json
{
  "success": false,
  "error": {
    "code": "invalid_payload",
    "message": "Payload JSON invalide"
  }
}
```

---

## Règles importantes

- le payload doit toujours être un JSON valide
- `logs` doit toujours être un tableau
- tous les logs sont normalisés serveur
- les champs sensibles sont filtrés
- le fingerprint est recalculé serveur
- les query strings sont supprimées des URI


# 📦 Contrat LogEntry

Un `LogEntry` est :

- immutable
- toujours valide
- toujours normalisé

---

## Champs obligatoires

- message
- level
- domain
- env
- httpStatus
- client

---

## Champs auto-corrigés

- externalId
- createdAt
- clientDate
- uri
- ip

---

# 🔐 Fingerprint

Le fingerprint permet de regrouper les erreurs similaires.

Base :

```txt
level|httpStatus|domain|uri|env
```

Règles :

- lowercase
- trim
- suppression query string
- sha1 tronqué 16 caractères

Le fingerprint est toujours recalculé serveur.

---

# 🧼 Normalization

Toutes les données externes sont :

- normalisées
- limitées
- filtrées
- sécurisées

---

## Limites

- profondeur max : 5
- 50 éléments max
- string max : 1000 caractères

---

## Données sensibles filtrées

- password
- token
- authorization
- cookie

---

# 📁 Queue

La queue absorbe les pics de charge.

Principe :

```txt
1 log = 1 fichier
```

---

## Objectifs

- robustesse
- écriture atomique
- isolation des erreurs
- aucune dépendance forte

---

## Interdits

- workers permanents
- RabbitMQ
- Redis obligatoire
- architecture async complexe

---

# ⏱️ Cron

Le cron traite la queue :

```txt
queue
→ normalisation
→ persistence
→ suppression
```

---

## Règles

- traitement batch
- mémoire bornée
- traitement idempotent

---

# 💾 Base de données

La DB est pensée comme :

```txt
append-only store
```

Optimisée pour :

- écrire vite
- lire simplement
- purger facilement

---

## Tables principales

### projects

```txt
id
name
slug
retention_days
created_at
```

---

### api_tokens

```txt
id
project_id
name
token_hash
expires_at
last_used_at
revoked_at
created_at
```

---

### logs

```txt
id
external_id
project_id
fingerprint
level
http_status
domain
uri
method
env
client
message
context_json
created_at
client_date
```

---

# 🔎 Recherche

Toujours :

- pagination
- LIMIT
- requêtes bornées
- index ciblés

Jamais :

- SELECT *
- chargement massif mémoire

---

# 🧪 Tests

## Règle absolue

```txt
SI CE N’EST PAS TESTÉ → ÇA N’EXISTE PAS
```

---

## Priorités

1. Domain
2. Factory
3. Normalizer
4. Queue
5. Persistence
6. Search

---

## Cas critiques

- JSON invalide
- récursion
- payload corrompu
- disque plein
- DB indisponible
- données sensibles

---

# 🚫 Technologies volontairement évitées

- API Platform
- Messenger
- Event Bus
- Event Sourcing
- CQRS complexe
- Microservices
- React non justifié
- Vue non justifié

---

# 🎯 Objectif final

Construire un système :

- robuste
- lisible
- simple
- stable long terme
- maintenable
- compatible mutualisé

sans sur-ingénierie.

---

# 📌 Statut

Projet en cours de développement.
