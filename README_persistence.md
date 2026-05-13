# Persistence — Architecture complète

La couche `Persistence` est responsable de la persistance durable des `LogEntry`
normalisés dans la base SQL.

Elle appartient entièrement au :

```txt
WRITE SIDE
```

Priorité absolue :

```txt
écrire vite
écrire simplement
ne jamais casser
```

---

# 🎯 Responsabilités

La couche Persistence doit uniquement :

- recevoir des `LogEntry` valides
- transformer vers le format SQL
- persister en batch
- gérer les erreurs SQL
- isoler les erreurs techniques
- retourner des résultats explicites

---

# 🚫 Interdits

La couche Persistence ne doit jamais :

- normaliser les données
- recalculer le fingerprint
- valider le payload API
- contenir de logique métier complexe
- dépendre de Symfony dans le Domain
- dépendre du Dashboard
- dépendre de l’HTTP

---

# 🧠 Philosophie

La base de données est pensée comme :

```txt
append-only store
```

Objectifs :

- écriture rapide
- lecture simple
- purge facile
- architecture stable long terme

---

# 📂 Architecture recommandée

```txt
src/Persistence/
│
├── Application/
│   ├── PersistLogBatchRequest.php
│   ├── PersistLogBatchHandler.php
│   └── PersistLogBatchResult.php
│
├── Domain/
│   ├── LogWriterInterface.php
│   ├── PersistenceException.php
│   └── PersistenceResult.php
│
├── Infrastructure/
│   ├── Doctrine/
│   │   ├── DoctrineLogWriter.php
│   │   ├── DoctrineLogPersister.php
│   │   └── Entity/
│   │       └── LogRecord.php
│   │
│   ├── Mapper/
│   │   └── LogEntryToRecordMapper.php
│   │
│   └── Exception/
│       └── DoctrinePersistenceException.php
│
└── Tests/
    ├── Unit/
    └── Integration/
```

---

# 🧱 Architecture détaillée

---

# Application/

La couche `Application` orchestre la persistence.

Elle coordonne :

```txt
LogEntry[]
    ↓
Writer
    ↓
PersistenceResult
```

Aucune logique SQL ici.

---

## PersistLogBatchCommand

Responsabilités :

- transporter les données
- encapsuler un batch
- garantir l’immutabilité

Contient :

```txt
- int projectId
- LogEntry[] entries
```

Règles :

- immutable
- aucun setter
- tableau borné

---

## PersistLogBatchHandler

Responsabilités :

```txt
recevoir batch
→ appeler writer
→ gérer erreurs
→ retourner résultat
```

Le handler ne doit jamais :

- manipuler Doctrine directement
- contenir du SQL
- contenir du mapping DB

---

## PersistLogBatchResult

Objet résultat explicite.

Contient :

```txt
- persistedCount
- failedCount
- errors[]
```

Toujours :

- immutable
- prédictible
- explicite

Jamais :

```txt
bool success
```

---

# Domain/

Le `Domain` contient uniquement :

- contrats
- invariants
- exceptions métier persistence

---

## Règle absolue

Le Domain :

```txt
ne dépend jamais de Symfony
```

---

## LogWriterInterface

Contrat principal de persistence.

Exemple :

```php
public function persist(
    int $projectId,
    array $entries,
): PersistenceResult;
```

---

## Règles importantes

Toujours :

- batch persistence
- retour explicite
- architecture simple

Jamais :

- persistence unitaire prioritaire
- exceptions silencieuses
- bool flou

---

## PersistenceResult

Objet immutable représentant le résultat de persistence.

Contient :

```txt
- persistedCount
- failedCount
- errors[]
```

---

## PersistenceException

Exception métier de persistence.

Responsabilités :

- erreurs métier persistence
- erreurs techniques génériques

Jamais :

- dépendance Doctrine directe

---

# Infrastructure/

La couche Infrastructure contient :

- Doctrine
- SQL
- DBAL
- mapping SQL
- erreurs techniques

---

# Doctrine/

Responsable :

```txt
LogEntry
→ SQL
```

---

## DoctrineLogWriter

Implémentation principale :

```txt
LogWriterInterface
```

Responsabilités :

- batch insert
- transaction bornée
- flush contrôlé
- clear contrôlé
- isolation des erreurs DB

---

## Règles importantes

Toujours :

- batch borné
- flush régulier
- clear régulier
- mémoire bornée

Jamais :

- 10 000 entities Doctrine en mémoire
- transaction géante
- flush final unique

---

## Taille batch recommandée

```txt
100 à 500 logs
```

---

## DoctrineLogPersister

Service technique Doctrine.

Responsabilités :

```txt
persist
flush
clear
```

Uniquement technique.

Aucune logique métier.

---

# Entity/

---

## LogRecord

Entity Doctrine ultra simple.

Responsabilités :

```txt
mapping SQL uniquement
```

L’entity ne doit jamais contenir :

- logique métier
- validation
- normalisation
- helpers
- services

---

# Mapper/

---

## LogEntryToRecordMapper

Responsabilités :

```txt
LogEntry
→ LogRecord
```

Toujours :

- mapping explicite
- transformation simple
- aucune magie

Jamais :

```txt
LogRecord → LogEntry
```

---

# 🔥 Règles Doctrine IMPORTANTES

---

# ❌ Interdits

Doctrine ne doit jamais utiliser :

- listeners
- subscribers
- lifecycle callbacks
- relations profondes
- cascade persist
- cascade remove
- eager loading
- logique métier dans les entities

---

# ✅ Toujours

Toujours privilégier :

- SQL simple
- batch insert
- flush borné
- clear régulier
- index ciblés
- requêtes bornées

---

# 📦 Flux complet

```txt
QueueFile
    ↓
QueueConsumer
    ↓
LogEntryFactory
    ↓
PersistLogBatchHandler
    ↓
DoctrineLogWriter
    ↓
LogEntryToRecordMapper
    ↓
DoctrineLogPersister
    ↓
Database
```

---

# 💾 Structure SQL

Table principale :

```txt
logs
```

---

## Colonnes recommandées

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

# 📌 Types SQL recommandés

```txt
id                BIGINT
external_id       CHAR(36)
project_id        BIGINT
fingerprint       CHAR(16)

level             VARCHAR(20)
http_status       SMALLINT

domain            VARCHAR(255)
uri               VARCHAR(1000)
method            VARCHAR(10)

env               VARCHAR(50)
client            VARCHAR(50)

message           TEXT
context_json      JSON

created_at        DATETIME
client_date       DATETIME NULL
```

---

# 📇 Index recommandés

Toujours indexer :

```txt
project_id
created_at
fingerprint
level
env
http_status
```

---

# 🚫 Éviter

Éviter :

- index inutiles
- index multiples massifs
- fulltext prématuré

---

# ⚡ Performance

La priorité absolue reste :

```txt
les performances d’écriture
```

---

# Toujours

- batch insert
- mémoire bornée
- SQL simple
- allocations minimales
- architecture explicite

---

# Jamais

- ORM complexe
- abstraction excessive
- hydratation inutile
- relations profondes
- sur-ingénierie

---

# 💥 Gestion erreurs SQL

Le système ne doit jamais planter entièrement à cause :

- d’un log corrompu
- d’un batch invalide
- d’une erreur SQL ponctuelle
- d’une DB temporairement indisponible

---

# Objectif

Toujours :

```txt
isoler l’erreur
continuer le traitement
```

---

# Stratégie robuste recommandée

```txt
batch 500
    ↓
échec
    ↓
retry batch 100
    ↓
échec
    ↓
retry unitaire
    ↓
log corrompu isolé
```

---

# 🔁 Idempotence

La persistence doit rester :

```txt
idempotente
```

Même si :

- le cron relance un batch
- un flush échoue partiellement
- le serveur redémarre

---

# Recommandations

Utiliser :

```txt
external_id unique
```

Pour éviter les doublons.

---

# 🧪 Tests obligatoires

---

# Unitaires

Tester :

- mapper
- result
- handler
- exceptions
- batch splitting

---

# Intégration

Tester :

- insertion SQL réelle
- batch insert
- rollback
- flush
- clear
- transaction
- contraintes SQL
- JSON massif

---

# 💣 Crash tests obligatoires

Tester :

- DB indisponible
- deadlock SQL
- table absente
- disque plein
- UTF-8 invalide
- mémoire limitée
- context énorme
- payload hostile

---

# 📌 Règle absolue

```txt
SI CE N’EST PAS TESTÉ
→ ÇA N’EXISTE PAS
```

---

# 🎯 Objectif final

Construire une couche Persistence :

- robuste
- ultra lisible
- prédictible
- maintenable
- stable long terme
- optimisée écriture

Même :

- sous forte charge
- avec données hostiles
- sur hébergement mutualisé
- avec ressources limitées