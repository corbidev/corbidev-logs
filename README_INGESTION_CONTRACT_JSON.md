# Mutualized Logging Platform — Contrat JSON d’Ingestion

# Objectif

Ce document définit le contrat officiel du payload JSON d’ingestion du système de logs mutualisé.

Le but :
- stabilité,
- robustesse,
- compatibilité long terme,
- écriture rapide,
- tolérance aux payloads hostiles.

Le système est conçu pour :
- hébergement mutualisé,
- Symfony 8,
- PHP 8.4,
- architecture synchrone,
- pipeline ultra robuste.

---

# Philosophie générale

Toutes les données externes sont considérées comme hostiles.

Le serveur doit :
- ne jamais casser,
- toujours répondre rapidement,
- normaliser les données,
- corriger ce qu’il peut,
- ignorer le reste.

Le système privilégie :
1. robustesse,
2. simplicité,
3. prédictibilité,
4. maintenabilité,
5. performances d’écriture.

---

# Versionnement

Le payload actuel correspond à :

```txt
v1
```

Les futures versions devront :
- rester backward compatibles,
- éviter les breaking changes,
- conserver les champs existants.

---

# Payload officiel v1

```json
{
  "message": "Paiement refusé",
  "level": "error",
  "domain": "billing",
  "env": "prod",
  "httpStatus": 500,
  "client": "symfony-api",

  "requestId": "req_01JT2R9YQ7R7W4M9YQ8T3D1KAB",
  "externalId": "01963610-f9d2-7f5b-a13c-3b2f5f0e2f91",

  "context": {
    "userId": 42,
    "orderId": 123
  },

  "extra": {
    "memoryPeakUsage": 12345678
  },

  "tags": {
    "feature": "checkout"
  },

  "exception": {
    "class": "RuntimeException",
    "message": "Card declined",
    "code": 0,

    "file": "/var/www/app/src/Service/Payment.php",
    "line": 42,

    "trace": [
      {
        "file": "/src/Ingestion/Application/LogIngestionHandler.php",
        "line": 88,
        "function": "pay"
      }
    ]
  },

  "request": {
    "method": "POST",
    "uri": "/checkout",
    "route": "checkout_pay",

    "headers": {
      "user-agent": "Mozilla/5.0"
    }
  },

  "server": {
    "hostname": "web-01",
    "phpVersion": "8.4"
  },

  "runtime": {
    "sapi": "fpm-fcgi"
  },

  "user": {
    "id": 42
  },

  "clientDate": "2026-05-09T10:00:00+00:00"
}
```

---

# Champs obligatoires

Les champs suivants doivent exister dans le payload final :

```txt
message
level
domain
env
httpStatus
client
```

Si absents :
- fallback automatique,
- correction serveur,
- jamais d’erreur fatale.

---

# Valeurs par défaut serveur

## message

Fallback :

```txt
Unknown error
```

---

## level

Valeurs autorisées :

```txt
debug
info
notice
warning
error
critical
alert
emergency
```

Fallback :

```txt
error
```

---

## domain

Fallback :

```txt
unknown
```

---

## env

Fallback :

```txt
prod
```

---

## httpStatus

Fallback :

```txt
500
```

Bornes :
- minimum : 100
- maximum : 599

---

## client

Fallback :

```txt
unknown-client
```

---

# requestId

## Objectif

Le `requestId` permet de suivre une requête sur tout son parcours.

Il sert à :
- corréler plusieurs logs,
- tracer une requête distribuée,
- suivre un utilisateur,
- reconstruire un incident complet.

---

# Exemple de propagation

```txt
Frontend
    ↓
API Gateway
    ↓
Symfony API
    ↓
Worker
    ↓
Paiement
    ↓
Webhook
```

Tous les logs doivent partager le même :

```txt
requestId
```

---

# Règles serveur du requestId

## Si présent

Le serveur :
- conserve le requestId,
- le normalise,
- le propage.

---

## Si absent

Le serveur génère automatiquement :
- un ULID,
ou
- un UUID v7.

Format recommandé :

```txt
req_01JT2R9YQ7R7W4M9YQ8T3D1KAB
```

Pourquoi :
- sortable,
- lisible,
- stable,
- compact,
- performant en indexation.

---

# Validation du requestId

## Taille maximale

```txt
100 caractères
```

---

## Caractères autorisés

```txt
a-z
A-Z
0-9
-
_
.
```

Tout le reste :
- supprimé,
ou
- fallback génération serveur.

---

# externalId

Le `externalId` représente l’identifiant unique du log.

Si absent :
- généré automatiquement serveur.

Exemple :

```txt
evt_01JT2R9YQ7R7W4M9YQ8T3D1XYZ
```

---

# createdAt

Toujours généré serveur.

Le serveur ne fait jamais confiance à la date client.

---

# clientDate

Optionnel.

Si invalide :
- ignoré,
- null.

---

# URI

Toujours normalisée :
- trim,
- suppression query string,
- longueur maximale,
- suppression caractères invalides.

---

# IP

Toujours issue du serveur HTTP.

Jamais issue du payload client.

---

# Champs auto-corrigés

```txt
externalId
createdAt
clientDate
requestId
uri
ip
```

---

# Champs optionnels

Tous optionnels :

```txt
context
extra
tags
exception
request
server
runtime
user
```

Si invalides :
- remplacés par des structures vides.

---

# Structure des sections optionnelles

## context

Contient :
- données métier,
- informations métier utiles,
- contexte applicatif.

Exemple :

```json
{
  "userId": 42,
  "orderId": 123
}
```

---

## extra

Contient :
- données techniques,
- métriques runtime,
- informations système.

Exemple :

```json
{
  "memoryPeakUsage": 12345678
}
```

---

## tags

Contient :
- labels simples,
- filtres de recherche,
- catégorisation.

Exemple :

```json
{
  "feature": "checkout"
}
```

---

# Exception

## Structure cible

```json
{
  "class": "RuntimeException",
  "message": "Erreur",
  "code": 0,
  "file": "/file.php",
  "line": 12,
  "trace": []
}
```

---

# Trace

Chaque frame :

```json
{
  "file": "/file.php",
  "line": 12,
  "function": "foo"
}
```

Maximum :
- 50 frames.

---

# request

## Structure

```json
{
  "method": "POST",
  "uri": "/checkout",
  "route": "checkout_pay",
  "headers": {}
}
```

---

# server

## Structure

```json
{
  "hostname": "web-01",
  "phpVersion": "8.4"
}
```

---

# runtime

## Structure

```json
{
  "sapi": "fpm-fcgi"
}
```

---

# user

## Structure

```json
{
  "id": 42
}
```

---

# Règles de normalisation

## Profondeur maximale

```txt
5
```

---

## Nombre maximal d’éléments par tableau

```txt
50
```

---

## Taille maximale des chaînes

```txt
1000 caractères
```

---

# Données sensibles

Les clés suivantes doivent être filtrées partout dans le payload :

```txt
password
passwd
pwd
token
authorization
cookie
set-cookie
secret
api-key
apikey
```

Valeur remplacée par :

```txt
[FILTERED]
```

---

# Fingerprint

Le fingerprint est toujours recalculé serveur.

Base :

```txt
level|httpStatus|domain|uri|env
```

Transformations :
- lowercase,
- trim,
- suppression query string,
- sha1,
- troncature 16 caractères.

---

# Différence entre requestId et fingerprint

## requestId

Permet de retrouver :

```txt
Tous les logs d'une seule requête
```

---

## fingerprint

Permet de retrouver :

```txt
Toutes les erreurs similaires
```

Les deux sont complémentaires.

---

# Réponses API

# Succès

HTTP :

```txt
202 Accepted
```

Body :

```json
{
  "success": true
}
```

---

# JSON invalide

HTTP :

```txt
400 Bad Request
```

Body :

```json
{
  "success": false,
  "error": "invalid_json"
}
```

---

# Payload trop volumineux

HTTP :

```txt
413 Payload Too Large
```

Body :

```json
{
  "success": false,
  "error": "payload_too_large"
}
```

---

# Champs inconnus

Le serveur :
- ne rejette jamais les champs inconnus,
- ignore le surplus,
- conserve la compatibilité.

---

# Règles de robustesse

Le système doit survivre :
- JSON invalide,
- UTF8 corrompu,
- récursion,
- arrays gigantesques,
- payloads malveillants,
- types incohérents,
- disque plein,
- DB indisponible.

---

# Premier flow cible

```txt
HTTP POST /api/logs
        ↓
validation minimale
        ↓
normalization
        ↓
LogEntry
        ↓
queue filesystem
        ↓
HTTP 202
```

Sans :
- DB,
- dashboard,
- recherche.

Uniquement :
- robustesse,
- queue disque,
- prédictibilité.

---

# Architecture cible minimale

```txt
src/

Ingestion/
├── Application/
│   └── IngestLogHandler.php
│
├── Infrastructure/
│   ├── Http/
│   │   └── ApiIngestController.php
│   │
│   └── Queue/
│       └── FileQueueWriter.php

Log/
├── Domain/
│   ├── LogEntry.php
│   ├── LogLevel.php
│   ├── Fingerprint.php
│   └── Exception/
│
├── Application/
│   └── Normalizer/
│       └── LogPayloadNormalizer.php
```

---

# Prochaine étape

Écrire les tests du normalizer avant le code.

Priorités :
- JSON invalide,
- payload corrompu,
- récursion,
- données sensibles,
- UTF8 invalide,
- arrays énormes,
- types incohérents,
- champs manquants,
- requestId absent,
- requestId invalide.

---

# Règle finale

SI CE N’EST PAS TESTÉ → ÇA N’EXISTE PAS
