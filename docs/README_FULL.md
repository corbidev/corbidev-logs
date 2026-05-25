
# 📘 SYSTÈME DE LOGS — DOCUMENTATION COMPLÈTE

> Statut au 25/05/2026 : ce document combine du livré et du cible.
> Les modules Ingestion, ApiToken, Project, Search et Dashboard sont implémentés.

---

# 🧠 1. VISION GLOBALE

## Objectif

Construire un système de logs :

- **FIABLE** : ne casse jamais, même avec des données corrompues
- **PRÉVISIBLE** : aucune logique cachée

basé sur PSR-3
---

## Principe fondamental

> ❗ Un système de logs ne doit JAMAIS échouer à cause des données qu’il reçoit.

---

# 🧱 2. ARCHITECTURE GLOBALE

## Diagramme principal

```
[API / MONOLOG]
  ↓
POST /api/logs (Bearer token opaque)
  ↓
Normalizer -> Factory -> QueueWriter
  ↓
app:queue:process
  ↓
Doctrine DBAL (table logs)
  ↓
Search borné -> Dashboard (liste + détail)
```

---

## Règles fondamentales

- flux à sens unique
- aucun appel inverse
- aucune dépendance circulaire

## Authentification ingestion

- protocole : `Authorization: Bearer <token_opaque>`
- modèle de sécurité : token opaque généré aléatoirement
- persistence : hash du token uniquement (`token_hash`)
- contrôles d'accès : token actif requis
- refus explicites : token absent, token révoqué, token expiré
- JWT : non utilisé sur la route d'ingestion

---

# 📂 3. STRUCTURE DU PROJET

```
src/
├── ApiToken/
├── Dashboard/
├── Ingestion/
├── Log/
├── Persistence/
├── Project/
├── Queue/
├── Search/
└── Shared/
```

---

# 📦 4. CONTRAT LOGENTRY

## Obligatoires strict

- message (string non vide)
- level (INFO, WARNING, ERROR, CRITICAL)
- domain
- env
- httpStatus
- client

---

## Obligatoires auto-corrigés

- externalId → UUID généré
- createdAt → date serveur
- clientDate → fallback createdAt
- uri → "/"
- ip → "0.0.0.0"

---

## Optionnels

- userId
- context (toujours array)

---

## Exemple final

```json
{
  "externalId": "uuid",
  "requestId": "clé de suivi d'une requête",
  "domain": "corbisier.fr",
  "uri": "/login",
  "message": "User login failed",
  "level": "ERROR",
  "env": "prod",
  "client": "web",
  "httpStatus": 401,
  "createdAt": "2026-04-30T12:00:00Z",
  "clientDate": "2026-04-30T12:00:00Z",
  "fingerprint": "abc123",
  "context": {}
}
```

---

# 🏭 5. FACTORY

## Rôle

Transformer toute entrée en LogEntry valide.

## Responsabilités

- validation métier
- fallback des champs
- génération fingerprint
- parsing URI

## Interdits

- pas de JSON
- pas d’écriture disque

---

# 🔐 6. FINGERPRINT

## Objectif

Regrouper les erreurs identiques.

## Construction

```
level|httpStatus|domain|uri|env
```

## Hash

- sha1
- tronqué à 16 caractères

---

# 🌐 7. URI & QUERY

## URI

- sans query string
- normalisée

## Query

placée dans context :

```json
"context": {
  "query": {
    "id": "42"
  }
}
```

---

# 🧼 8. NORMALIZER

## Objectif

Empêcher tout crash JSON.

---

## Limites

- profondeur max : 5
- éléments max : 50
- string max : 1000

---

## Gestion des types

| type | résultat |
|------|--------|
| object | "[object]" |
| exception | structure |
| resource | "[resource]" |
| recursion | "[circular]" |

---

## Sécurité

- password → "[FILTERED]"
- token → "[FILTERED]"

---

# 📁 9. QUEUE

## Principe

1 log = 1 fichier

## Nom

```
2026-04-30T12-00-00_uuid.queue
```

---

## Règles

- écriture atomique
- pas de lock complexe

---

# ⏱️ 10. CRON

## Flow

```
queue → success → delete
queue → fail → .error
.error → retry
fail → /failed + mail
```

---

## Mail

- natif uniquement 
- jamais Symfony

---

# 💾 11. PERSISTENCE

## Rôle

Stockage final :

- fichier
- ou base de données

---

## Règles

- indépendant
- utilisé uniquement par cron

---

# 🧪 12. TESTING

## Règle

> SI PAS TESTÉ → N’EXISTE PAS

---

## Priorités

1. Domain
2. Factory
3. Normalizer
4. Queue
5. Cron

---

## Cas critiques

- récursion
- données invalides
- disque plein
- JSON cassé

---

# 🔒 13. SÉCURITÉ

- jamais de données sensibles
- jamais confiance au client
- toujours normaliser

---

# 🚀 14. RÉSUMÉ FINAL

✔ système robuste  
✔ zéro boucle  
✔ zéro crash  
✔ prêt prod  

---

# 📌 FIN

Ce document est la référence officielle du système de logs.
