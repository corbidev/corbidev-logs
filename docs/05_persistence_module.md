# Module Persistence

## Table des matières
1. [Vue d'ensemble](#vue-densemble)
2. [Architecture](#architecture)
3. [Application Layer](#application-layer)
4. [Domain Layer](#domain-layer)
5. [Énumérations](#énumérations)
6. [Infrastructure Layer](#infrastructure-layer)
7. [Configuration et limites](#configuration-et-limites)

---

## Vue d'ensemble

Le module `Persistence` orchestre la **persistance durable des LogEntry validés** dans une base de données SQL. C'est la couche qui relie le domaine pur à l'implémentation technique (Doctrine, DBAL, SQL).

### Responsabilités principales
- **Orchestrer la persistence** : Gérer le batch de logs vers la base de données
- **Protéger le pipeline** : Isoler les erreurs SQL sans casser le processus global
- **Mapper Domain → SQL** : Transformer les LogEntry en enregistrements persistables
- **Garantir la robustesse** : Aucune exception technique ne remonte au-dessus
- **Fournir des résultats explicites** : Chaque operation retourne un résultat clair
- **Sécuriser les données** : Valider, borner et nettoyer avant persistence

### Principes fondamentaux
- **Abstraction minimale** : Interface simple et directe
- **Robustesse sans crash** : Toute erreur est capturée et normalisée
- **Explicité** : Pas de booléens ambigus, résultats structurés
- **Isolation des erreurs** : Une erreur d'un log ne casse pas le batch entier
- **SQL explicite** : Pas d'ORM complexe, code clair et contrôlable
- **Performance écriture** : Optimisé pour l'insertion en masse

---

## Architecture

```
Persistence/
├── Enum/                      # Énumérations métier
│   └── PersistenceErrorCode.php # Codes d'erreur stables
│
├── Constantes/                # Limites techniques
│   └── PersistenceLimits.php  # Configuration centralisée
│
├── Domain/                    # Cœur du domaine persistence
│   ├── LogWriterInterface.php # Contrat persistence
│   ├── PersistenceResult.php  # Résultat explicite
│   └── PersistenceException.php # Exception métier
│
├── Application/               # Orchestration application
│   ├── PersistLogBatchHandler.php   # Handler batch
│   ├── PersistLogBatchRequest.php   # Requête batch
│   └── PersistLogBatchResult.php    # Résultat batch
│
└── Infrastructure/            # Implémentation SQL
    ├── Doctrine/
    │   └── DoctrineLogWriter.php    # Writer DBAL/Doctrine
    ├── Entity/
    │   └── LogRecord.php            # Entity Doctrine mappée
    └── Mapper/
        └── LogEntryToRecordMapper.php # Mapper Domain→SQL
```

---

## Application Layer

### `PersistLogBatchHandler.php`

**Handler applicatif** qui orchestre la persistance durable d'un batch de logs normalisés.

#### Responsabilités
- Orchestrer la persistence robuste
- Protéger la couche persistence d'appels inutiles
- Éviter les crashes brutaux
- Capturer les erreurs inattendues

#### Important
Ce handler **NE DOIT JAMAIS** :
- Valider les logs (déjà valides)
- Normaliser les données (déjà normalisées)
- Contenir du SQL
- Dépendre de Symfony
- Dépendre de Doctrine
- Laisser fuiter une exception

#### Dépendances
- `LogWriterInterface $writer` - Writer de persistence

#### Méthodes

##### Constructor
```php
public function __construct(
    private LogWriterInterface $writer,
)
```

##### `public function handle(PersistLogBatchRequest $request): PersistenceResult`

Persiste un batch de logs.

**Paramètres :**
- `PersistLogBatchRequest $request` - Requête de persistence

**Retour :** `PersistenceResult` - Résultat explicite

**Garanties :**
- Ne provoque jamais d'erreur fatale
- Aucune exception n'est laissée fuir
- Comportement stable et prévisible

**Processus :**
1. Vérifier que le batch n'est pas vide
2. Appeler le writer de persistence
3. Normaliser défensivement le résultat
4. En cas d'erreur : créer un résultat d'échec sécurisé

**Exemple :**
```php
$request = new PersistLogBatchRequest($logEntries);
$result = $handler->handle($request);

if ($result->hasFailures()) {
    logger()->error('Persistence failed', $result->toArray());
}
```

---

### `PersistLogBatchRequest.php`

**Requête immutable** qui transporte un batch de LogEntry validés vers la couche de persistence.

#### Responsabilités
- Transporter les logs de manière immuable
- Protéger le batch contre les modifications
- Filtrer défensivement les données invalides
- Éviter les crashes runtime

#### Important
Cette classe **NE DOIT JAMAIS** :
- Valider les logs
- Normaliser les données
- Persister les données
- Dépendre de Symfony
- Contenir de logique métier

Les LogEntry reçus doivent déjà être :
- ✓ Valides
- ✓ Normalisés
- ✓ Sécurisés
- ✓ Immutables

#### Propriétés
- `entries`: array - List<LogEntry> filtrée et réindexée

#### Méthodes

##### Constructor
```php
public function __construct(iterable $entries)
```

**Paramètres :**
- `iterable<mixed> $entries` - Itérable potentiellement invalide

**Garanties :**
- Filtre uniquement les LogEntry valides
- Réindexe le tableau
- Aucune modification externe possible

##### `public function getEntries(): array`

Retourne les logs du batch.

**Retour :** `list<LogEntry>` - Tableau filtré et réindexé

##### `public function count(): int`

Retourne le nombre de logs.

**Retour :** `int` - Comptage

##### `public function isEmpty(): bool`

Indique si le batch est vide.

**Retour :** `bool`

##### `public function first(): ?LogEntry`

Retourne le premier log du batch.

**Retour :** `LogEntry|null`

##### `public function last(): ?LogEntry`

Retourne le dernier log du batch.

**Retour :** `LogEntry|null`

---

### `PersistLogBatchResult.php`

**Résultat explicite** d'une persistence batch.

#### Responsabilités
- Exposer un résultat immutable
- Fournir des compteurs fiables
- Encapsuler les erreurs techniques
- Rester prédictible et robuste

#### Important
Cet objet ne doit **JAMAIS** :
- Lancer d'exception
- Contenir de logique SQL
- Dépendre de Doctrine
- Dépendre de Symfony
- Contenir de logique métier complexe

#### Propriétés
- `persistedCount`: int - Logs persistés avec succès
- `failedCount`: int - Logs échoués
- `errors`: array - List<string> d'erreurs normalisées

#### Méthodes

##### Constructor
```php
public function __construct(
    int $persistedCount,
    int $failedCount,
    array $errors = [],
)
```

##### `public static function success(int $persistedCount): self`

Crée un résultat totalement réussi.

**Exemple :**
```php
$result = PersistLogBatchResult::success(42);
// → 42 logs persistés, 0 échecs
```

##### `public static function failure(int $failedCount, array $errors = []): self`

Crée un résultat totalement échoué.

**Exemple :**
```php
$result = PersistLogBatchResult::failure(10, ['SQL error']);
// → 0 logs persistés, 10 échecs
```

##### `public static function partial(int $persistedCount, int $failedCount, array $errors = []): self`

Crée un résultat partiellement réussi.

**Exemple :**
```php
$result = PersistLogBatchResult::partial(40, 10, ['Error: ..']);
// → 40 logs persistés, 10 échecs
```

##### `public static function empty(): self`

Crée un résultat vide.

```php
$result = PersistLogBatchResult::empty();
// → 0 logs persistés, 0 échecs
```

##### `public function getPersistedCount(): int`

Nombre de logs persistés avec succès.

##### `public function getFailedCount(): int`

Nombre de logs échoués.

##### `public function getErrors(): array`

Retourne les erreurs normalisées.

**Retour :** `list<string>`

##### `public function getTotalCount(): int`

Nombre total de logs traités.

**Retour :** `int` - persistedCount + failedCount

##### `public function hasPersistedLogs(): bool`

Indique si au moins un log a été persisté.

##### `public function hasFailures(): bool`

Indique si au moins un log a échoué.

##### `public function hasNoFailures(): bool`

Indique si aucun log n'a échoué.

##### `public function hasErrors(): bool`

Indique si des erreurs sont présentes.

##### `public function toArray(): array`

Retourne une représentation tableau stable.

**Retour :**
```php
[
    'persisted_count' => int,
    'failed_count' => int,
    'total_count' => int,
    'errors' => list<string>,
]
```

##### `public static function merge(iterable $results): self`

Fusionne plusieurs résultats batch.

**Utilisation :**
- Retry partiels
- Sous-batches
- Persistence dégradée

---

## Domain Layer

### `LogWriterInterface.php`

**Contrat d'écriture durable** des logs - Point d'entrée unique de persistance.

#### Responsabilités
- Définir le contrat de persistence
- Protéger le domaine de l'implémentation technique
- Garantir la robustesse
- Fournir l'abstraction minimale nécessaire

#### Garanties
- Les LogEntry reçus sont **déjà valides**
- Les LogEntry sont **immutables**
- Aucune normalisation ici
- Aucune logique métier ici
- Aucune lecture ici

#### Important
L'implémentation ne doit **JAMAIS** :
- Modifier les LogEntry
- Relancer des exceptions techniques brutes
- Effectuer de SELECT massif
- Dépendre du frontend
- Contenir de logique Symfony

##### Règle critique
Une erreur de persistence ne doit jamais :
- ✗ Casser le processus complet
- ✗ Faire perdre tout le batch
- ✗ Provoquer un état incohérent

Les implémentations doivent :
- ✓ Isoler les erreurs
- ✓ Retourner un résultat explicite
- ✓ Rester idempotentes autant que possible
- ✓ Privilégier les écritures simples

#### Exemples d'implémentation
- `DoctrineLogWriter` - Persistence MySQL/MariaDB
- `NullLogWriter` - Persistence vide (tests)
- `BufferedLogWriter` - Buffering temporaire

#### Méthode

##### `public function persist(array $entries): PersistenceResult`

Persiste un batch de logs normalisés.

**Paramètres :**
- `list<LogEntry> $entries` - Batch de logs validés

**Retour :** `PersistenceResult` - Résultat explicite

**Contrat :**
- Le tableau peut être vide
- Chaque entrée DOIT être un LogEntry valide
- L'ordre des logs DOIT être conservé
- Aucune exception ne doit fuiter

**Comportement attendu en cas d'erreur :**
- Capturer les exceptions techniques
- Retourner un PersistenceResult explicite
- Ne jamais interrompre brutalement le processus

---

### `PersistenceResult.php`

Représente le **résultat explicite** d'une opération de persistence.

#### Objectifs
- ✓ Immutable
- ✓ Prédictible
- ✓ Robuste
- ✓ Sans ambiguïté
- ✓ Résistant aux payloads hostiles

#### Invariants
- Compteurs toujours valides (≥ 0)
- Erreurs normalisées
- Structure bornée
- Aucune donnée non scalaire
- État toujours cohérent

#### États possibles
- **success** - Tous les logs persistés
- **failure** - Tous les logs échoués
- **partial** - Certains logs persistés, certains échoués
- **nothing_to_persist** - Batch vide (distinct du succès)

#### Propriétés
- `persistedCount`: int - Logs persistés
- `failedCount`: int - Logs échoués
- `errors`: array - List<string> d'erreurs

#### Méthodes (voir section Application Layer pour les détails complets)

---

### `PersistenceException.php`

**Exception métier de persistence** encapsulant les erreurs de manière sécurisée.

#### Responsabilités
- Encapsuler les erreurs de persistence
- Fournir un code métier stable
- Exposer un contexte borné et sécurisé
- Garantir des messages prédictibles
- Protéger contre les payloads hostiles

#### Invariants
- Immutable
- Aucun payload massif
- Aucun secret exposé
- Aucun contexte non borné
- Aucun objet complexe
- Aucun tableau imbriqué

#### Propriétés
- `errorCode`: PersistenceErrorCode - Code métier stable
- `context`: array - Contexte sécurisé (scalar|null uniquement)

#### Méthodes statiques factory

##### `public static function databaseConnectionFailed(array $context = [], ?Throwable $previous = null): self`

Erreur de connexion à la base de données.

**Exemple :**
```php
throw PersistenceException::databaseConnectionFailed(
    ['host' => 'localhost', 'error' => 'Connection refused']
);
```

##### `public static function queryExecutionFailed(array $context = [], ?Throwable $previous = null): self`

Erreur d'exécution SQL.

##### `public static function emptyBatch(array $context = []): self`

Le batch à persister est vide.

##### `public static function batchTooLarge(array $context = []): self`

Le batch dépasse la limite autorisée.

##### `public static function invalidPayload(array $context = [], ?Throwable $previous = null): self`

Le payload de persistence est invalide.

##### `public static function timeout(array $context = [], ?Throwable $previous = null): self`

Timeout de la base de données.

##### `public static function deadlock(array $context = [], ?Throwable $previous = null): self`

Deadlock détecté.

##### `public static function transactionFailed(array $context = [], ?Throwable $previous = null): self`

Échec de transaction.

---

## Énumérations

### `PersistenceErrorCode.php`

Énumération des **codes d'erreurs métier** de persistence.

#### Responsabilités
- Fournir des codes stables
- Éviter les magic numbers
- Standardiser les erreurs

#### Cas énumérés

| Cas | Valeur | Description |
|-----|--------|-------------|
| `DATABASE_CONNECTION_FAILED` | 1000 | Erreur connexion DB |
| `QUERY_EXECUTION_FAILED` | 1001 | Erreur exécution SQL |
| `EMPTY_BATCH` | 1002 | Batch vide |
| `BATCH_TOO_LARGE` | 1003 | Batch trop volumineux |
| `INVALID_PAYLOAD` | 1004 | Payload invalide |
| `TIMEOUT` | 1005 | Timeout DB |
| `DEADLOCK` | 1006 | Deadlock détecté |
| `TRANSACTION_FAILED` | 1007 | Échec transaction |

---

## Configuration et limites

### `PersistenceLimits.php`

**Centralise les constantes techniques** de la couche Persistence.

#### Responsabilités
- Éviter les magic numbers
- Garantir la cohérence globale
- Simplifier la maintenance
- Rendre les limites explicites

#### Constantes

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `MAX_ERRORS` | 100 | Erreurs max conservées |
| `MAX_ERROR_LENGTH` | 1000 | Longueur max message erreur |
| `MAX_EXCEPTION_CONTEXT_ITEMS` | 20 | Éléments max contexte |
| `MAX_EXCEPTION_CONTEXT_KEY_LENGTH` | 100 | Longueur max clé contexte |
| `MAX_EXCEPTION_CONTEXT_VALUE_LENGTH` | 500 | Longueur max valeur contexte |

---

## Infrastructure Layer

### `DoctrineLogWriter.php`

**Writer Doctrine robuste** pour la persistence SQL.

#### Responsabilités
- Persister les LogEntry validés
- Isoler les erreurs SQL
- Protéger le pipeline de persistence
- Garantir des résultats explicites

#### Contraintes
- SQL explicite uniquement
- Aucun ORM complexe
- Aucune logique métier
- Mémoire bornée
- Batch borné
- Aucune dépendance au state interne

#### Philosophie
Le writer ne doit jamais casser le pipeline global.

Toute erreur :
- est capturée
- est normalisée
- est journalisée
- reste bornée

#### Constantes
- `MAX_BATCH_SIZE = 500` - Taille maximale d'un batch
- `TABLE_NAME = 'logs'` - Nom de la table SQL

#### Dépendances
- `Connection $connection` - Connexion Doctrine DBAL
- `LoggerInterface $logger` - Logger PSR-3

#### Méthodes

##### Constructor
```php
public function __construct(
    private Connection $connection,
    private LoggerInterface $logger,
)
```

##### `public function persist(array $entries): PersistenceResult`

Persiste une liste de logs.

**Paramètres :**
- `list<LogEntry> $entries` - Batch à persister

**Retour :** `PersistenceResult` - Résultat explicite

**Processus :**
1. Retourner nothingToPersist si batch vide
2. Limiter la taille du batch
3. Commencer une transaction
4. Itérer chaque log :
   - Insérer le log
   - Capturer les erreurs individuelles
5. Committer la transaction
6. Retourner le résultat approprié (success/failure/partial)

**Garanties :**
- Aucune exception ne remonte
- Les erreurs individuelles n'arrêtent pas le batch
- Un rollback sécurisé en cas d'erreur transaction
- Les résultats sont toujours explicites

---

### `LogRecord.php`

**Entity Doctrine** représentant un log persisté dans la base de données.

#### Responsabilités
- Mapping SQL explicite
- Stockage append-only
- Structure stable long terme
- Persistence optimisée écriture

#### Important
Cette entity **NE DOIT JAMAIS** contenir :
- Logique métier
- Validation métier
- Normalisation métier
- Lifecycle callbacks
- Services
- Helpers métier
- Relations Doctrine complexes

#### Philosophie
Cette entity est volontairement :
- Anémique (aucune logique)
- Prédictible
- Simple
- Optimisée écriture

Le Domain reste source de vérité :
- ✓ Immutable
- ✓ Validé
- ✓ Normalisé

#### Table SQL
- Nom : `logs`
- Type : InnoDB (transactions)

#### Colonnes principales

| Colonne | Type | Nullable | Unique | Description |
|---------|------|----------|--------|-------------|
| `id` | BIGINT UNSIGNED | ✗ | ✓ (PK) | Identifiant SQL |
| `external_id` | VARCHAR(36) | ✗ | ✓ | UUID externe |
| `project_id` | BIGINT UNSIGNED | ✗ | | Projet |
| `fingerprint` | VARCHAR(16) | ✗ | | Signature regroupement |
| `request_id` | VARCHAR(100) | ✗ | | ID corrélation |
| `level` | VARCHAR(20) | ✗ | | Niveau PSR-3 |
| `http_status` | SMALLINT UNSIGNED | ✗ | | Code HTTP |
| `domain` | VARCHAR(100) | ✗ | | Domaine applicatif |
| `uri` | VARCHAR(1000) | ✗ | | URI de requête |
| `method` | VARCHAR(10) | ✓ | | Méthode HTTP |
| `user_agent` | VARCHAR(500) | ✓ | | User-Agent |
| `env` | VARCHAR(50) | ✗ | | Environnement |
| `client` | VARCHAR(50) | ✗ | | Client source |
| `message` | TEXT | ✗ | | Message principal |
| `context_json` | JSON | ✗ | | Contexte JSON |
| `extra_json` | JSON | ✗ | | Données extra JSON |
| `ingestion_warnings_json` | JSON | ✗ | | Warnings JSON |
| `created_at` | DATETIME | ✗ | | Date serveur |
| `client_date` | DATETIME | ✓ | | Date client |
| `ip` | VARCHAR(45) | ✓ | | Adresse IP |

#### Index

| Nom | Colonnes | Type |
|-----|----------|------|
| `idx_logs_project_id` | project_id | |
| `idx_logs_created_at` | created_at | |
| `idx_logs_fingerprint` | fingerprint | |
| `idx_logs_request_id` | request_id | |
| `idx_logs_level` | level | |
| `idx_logs_env` | env | |
| `idx_logs_http_status` | http_status | |
| `idx_logs_project_created` | project_id, created_at | Composite |
| `idx_logs_project_fingerprint` | project_id, fingerprint | Composite |
| `idx_logs_project_request` | project_id, request_id | Composite |

#### Contraintes

| Nom | Type | Colonnes |
|-----|------|----------|
| `uniq_logs_external_id` | UNIQUE | external_id |

#### Propriétés et getters/setters

Toutes les propriétés ont des getters et setters correspondants.

**Exemple :**
```php
$record->setExternalId('550e8400-e29b-41d4-a716-446655440000');
$record->setFingerprint('a1b2c3d4e5f6g7h8');
$record->setMessage('Database connection error');
// ... autres setters
```

---

### `LogEntryToRecordMapper.php`

**Mapper explicite** Domain → Persistence qui transforme LogEntry vers LogRecord.

#### Responsabilités
- Transformer LogEntry → LogRecord
- Protéger la couche SQL
- Garantir des données persistables
- Sécuriser UTF-8
- Borner les tailles SQL
- Normaliser les payloads JSON
- Préserver les warnings ingestion

#### Important
Ce mapper **NE DOIT JAMAIS** :
- Faire de persistence
- Utiliser Doctrine directement
- Contenir de logique métier
- Recalculer le fingerprint
- Modifier les invariants métier
- Throw sur données hostiles

#### Philosophie
Toute donnée externe est hostile.

Le mapper agit comme :
```
LogEntry
    ↓
sanitation SQL
    ↓
LogRecord
```

#### Constantes de limites

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `DOMAIN_MAX_LENGTH` | 255 | Domaine max |
| `URI_MAX_LENGTH` | 1000 | URI max |
| `METHOD_MAX_LENGTH` | 20 | Méthode max |
| `USER_AGENT_MAX_LENGTH` | 500 | User-Agent max |
| `ENV_MAX_LENGTH` | 50 | Env max |
| `CLIENT_MAX_LENGTH` | 50 | Client max |
| `LEVEL_MAX_LENGTH` | 20 | Level max |
| `FINGERPRINT_MAX_LENGTH` | 16 | Fingerprint max |
| `REQUEST_ID_MAX_LENGTH` | 100 | RequestId max |
| `EXTERNAL_ID_MAX_LENGTH` | 36 | External ID max |
| `IP_MAX_LENGTH` | 45 | IP max (IPv6) |
| `MAX_JSON_DEPTH` | 5 | Profondeur JSON max |
| `MAX_JSON_ITEMS` | 50 | Éléments JSON max |
| `MAX_STRING_LENGTH` | 1000 | String JSON max |
| `MAX_JSON_KEY_LENGTH` | 100 | Clé JSON max |

#### Méthodes

##### `public function map(int $projectId, LogEntry $entry): LogRecord`

Mappe un LogEntry vers LogRecord.

**Paramètres :**
- `int $projectId` - Identifiant du projet
- `LogEntry $entry` - LogEntry à mapper

**Retour :** `LogRecord` - Record persistable

**Processus :**
1. Créer une instance LogRecord
2. Mapper chaque champ :
   - Trim/lowercase/sanitize
   - Troncature aux limites SQL
   - Gestion des nullable
3. Mapper les contextes JSON
4. Mapper les warnings d'ingestion
5. Retourner le record

**Garanties :**
- Toutes les données sont sécurisées
- UTF-8 correctement traité
- Pas d'exception levée
- Toutes les tailles respectées

**Exemple :**
```php
$logEntry = $factory->create($payload);
$record = $mapper->map($projectId, $logEntry);
$logWriter->persist([$logEntry]);
```

---

## Flux de traitement complet

```
LogEntry validé (depuis Log module)
        ↓
PersistLogBatchRequest
        ↓
PersistLogBatchHandler
        ↓
LogWriterInterface
        ↓
DoctrineLogWriter (SQL)
        ↓
LogEntryToRecordMapper
        ↓
LogRecord (Doctrine Entity)
        ↓
INSERT INTO logs
        ↓
PersistenceResult (explicite)
```

---

## Bonnes pratiques d'utilisation

### 1. Toujours traiter les résultats explicitement
```php
// ✓ BON
$result = $handler->handle($request);
if ($result->hasFailures()) {
    logger()->error('Persistence failed', $result->toArray());
}

// ✗ MAUVAIS
$handler->handle($request); // Ignorer le résultat
```

### 2. Utiliser les factory méthodes de résultat
```php
// ✓ BON - Explicite
$result = PersistenceResult::success(42);
$result = PersistenceResult::failure(10, ['Error']);
$result = PersistenceResult::partial(40, 2, ['Partial error']);

// ✗ MAUVAIS - Ambiguïté
$result = new PersistenceResult(0, 1); // Quoi? C'est un fail?
```

### 3. Merger les résultats batch
```php
// ✓ BON - Sous-batches
$results = [];
foreach (array_chunk($entries, 100) as $chunk) {
    $request = new PersistLogBatchRequest($chunk);
    $results[] = $handler->handle($request);
}
$finalResult = PersistenceResult::merge($results);
```

### 4. Ne pas ignorer les warnings d'ingestion
```php
// ✓ BON - Tracer les anomalies
$logEntry = $factory->create($payload);
foreach ($logEntry->getIngestionWarnings() as $warning) {
    logger()->info('Ingestion warning', [
        'field' => $warning->field(),
        'type' => $warning->type()->value,
    ]);
}
```

### 5. Valider les logs AVANT de créer le batch
```php
// ✓ BON
$logEntry = $factory->create($payload); // Valide et normalise
$request = new PersistLogBatchRequest([$logEntry]);
$result = $handler->handle($request);

// ✗ MAUVAIS - Mauvaises données
$request = new PersistLogBatchRequest([null, 'invalid', $logEntry]);
// La request filtre silencieusement, mais c'est signe d'un problème
```

---

## Gestion d'erreurs

### Erreurs de connection SQL
```php
try {
    $result = $writer->persist($entries);
} catch (PersistenceException $e) {
    if ($e->getCode() === PersistenceErrorCode::DATABASE_CONNECTION_FAILED->value) {
        // Retry avec exponential backoff
        retry($operation);
    }
}
```

### Batch trop volumineux
```php
// Le writer limite automatiquement à MAX_BATCH_SIZE
// Les logs au-delà sont ignorés silencieusement
// Un warning est loggé
foreach (array_chunk($entries, 500) as $chunk) {
    $request = new PersistLogBatchRequest($chunk);
    $result = $handler->handle($request);
}
```

### Erreurs partielles
```php
$result = $handler->handle($request);

if ($result->hasPersistedLogs() && $result->hasFailures()) {
    // Cas partial - certains logs réussis, certains échoués
    logger()->warning('Partial persistence', [
        'persisted' => $result->getPersistedCount(),
        'failed' => $result->getFailedCount(),
        'errors' => $result->getErrors(),
    ]);
}
```

---

## Performance et optimisations

### Batch sizing
```php
// Optimal pour la plupart des cas
$batchSize = 100; // Équilibre entre mémoire et performance

foreach (array_chunk($entries, $batchSize) as $chunk) {
    $request = new PersistLogBatchRequest($chunk);
    $result = $handler->handle($request);
}
```

### Limiter les erreurs loggées
```php
// Les erreurs sont capturées et normalisées
// Elles restent bornées à MAX_ERRORS
// Les erreurs anciennes sont supprimées automatiquement
```

### Indexing strategique
```
Les index optimisent les requêtes de recherche :
- (project_id, created_at) pour les recherches temporelles
- fingerprint pour le regroupement
- request_id pour la corrélation
```

---

## Évolution et stabilité

Le module Persistence est conçu pour **l'évolution progressive** :

### Couches stables
- **Domain Interface** : Très stable, contrats clairs
- **Énumérations** : Très stables, ajout uniquement
- **Entity Doctrine** : Stable, champs nouveaux ajoutés

### Couches évolutives
- **Writer implémentations** : Remplaçables (NullWriter, BufferedWriter, etc.)
- **Mapper** : Peut être amélioré pour nouvelles colonnes
- **SQL optimisations** : Peuvent être affinées

---

## Dépannage

### Tous les logs échouent
**Cause :** Erreur de connection DB  
**Solution :** Vérifier la configuration Doctrine, les permissions

### Certains logs échouent silencieusement
**Cause :** Contrainte UNIQUE violation (externe_id doublon)  
**Solution :** Vérifier les logs dupliqués, l'idempotence

### Timeout de persistence
**Cause :** Batch trop volumineux, DB lente  
**Solution :** Réduire la taille du batch, optimiser les index

### Deadlock détecté
**Cause :** Concurrence écriture  
**Solution :** Retry avec exponential backoff, réduire isolation

