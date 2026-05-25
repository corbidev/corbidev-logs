# Architecture

## Vue d'ensemble pipeline

```txt
POST /api/logs
-> validation minimale
-> normalisation
-> queue disque
-> consumer batch
-> persistence SQL
-> recherche/dashboard
```

## Principes

- robustesse prioritaire
- comportement explicite
- bornage partout (batch/pagination/retry)
- separation write side / read side

## Structure projet

```txt
.
├── docs/
├── logs/
├── database/
└── scripts/
```

```txt
logs/
├── src/
├── tests/
├── config/
├── templates/
└── migrations/
```

## Modules principaux

- ApiToken
- Ingestion
- Log
- Queue
- Persistence
- Search
- Dashboard
- Shared

## Zoom module Log

```txt
Normalizer -> Factory -> LogEntry -> Queue
```

Composants clefs:

- LogPayloadNormalizer
- LogEntryFactory
- FingerprintGenerator
- Value Objects Log

Fingerprint (base de calcul):

```txt
level|httpStatus|domain|uri|env
```

- hash tronque 16 caracteres
- stable pour une meme signature
