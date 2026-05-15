# Module Log

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Architecture](#architecture)
3. [Énumérations](#énumérations)
4. [Entités du Domaine](#entités-du-domaine)
5. [Value Objects](#value-objects)
6. [Application Layer](#application-layer)
7. [Infrastructure Layer](#infrastructure-layer)

---

## Vue d'ensemble

Le module `Log` est le **cœur central du système de gestion des logs**. Il représente l'implémentation DDD (Domain-Driven Design) d'un système robuste, scalable et résilient de gestion des entrées de log.

### Responsabilités principales

- **Encapsuler les logs valides** : Garantir que toute entrée de log respecte les invariants métier
- **Normaliser les données hostiles** : Traiter les payloads externes non fiables de manière sécurisée
- **Préserver l'intégrité des données** : Empêcher les états incohérents via l'immutabilité et les value objects
- **Tracer les anomalies** : Enregistrer tous les processus de normalisation et correction
- **Fournir des fingerprints stables** : Regrouper les logs similaires de manière déterministe
- **Écrire en queue disque** : Persister les logs de manière atomique et sûre

### Principes fondamentaux

- **Immutabilité** : Tous les objets sont `readonly` et ne peuvent pas être modifiés après création
- **Robustesse** : Aucun crash d'ingestion, fallback sur toutes les données hostiles
- **Prédictibilité** : Comportement déterministe et reproductible
- **Transparence** : Traçage de tous les processus de normalisation via `IngestionWarning`

---

## Architecture

```
Log/
├── Enum/                      # Énumérations fermées
│   ├── Environment.php        # Environnements applicatifs
│   ├── IngestionWarningType.php # Types de warnings
│   └── LogLevel.php           # Niveaux PSR-3
│
├── Domain/                    # Cœur du domaine métier
│   ├── Entity/
│   │   └── LogEntry.php       # Entité centrale immuable
│   ├── ValueObject/           # Value objects immuables
│   │   ├── Client.php
│   │   ├── Fingerprint.php
│   │   ├── HttpStatus.php
│   │   ├── IngestionWarning.php
│   │   ├── IpAddress.php
│   │   ├── Request.php
│   │   ├── RequestId.php
│   │   └── Uri.php
│   └── Exception/             # Exceptions métier
│       ├── InvalidClientException.php
│       ├── InvalidFingerprintException.php
│       ├── InvalidHttpStatusException.php
│       ├── InvalidIpAddressException.php
│       ├── InvalidLogEntryException.php
│       ├── InvalidRequestException.php
│       ├── InvalidRequestIdException.php
│       └── InvalidUriException.php
│
├── Application/               # Orchestration application
│   ├── Factory/
│   │   ├── LogEntryFactory.php         # Factory de création robuste
│   │   └── LogEntryFactoryInterface.php # Contrat
│   ├── Fingerprint/
│   │   └── FingerprintGenerator.php    # Générateur de signatures
│   └── Normalizer/
│       ├── LogNormalizerConfig.php     # Configuration technique
│       └── LogPayloadNormalizer.php    # Normalisation payloads
│
└── Infrastructure/            # Implémentation technique
    └── Queue/
        ├── FileQueueWriter.php         # Écriture disque atomique
        ├── QueueDirectoryManager.php   # Gestion des répertoires
        ├── QueueFilenameGenerator.php  # Génération noms fichiers
        └── Exception/
            ├── QueueDirectoryException.php
            ├── QueueFilenameException.php
            └── QueueWriteException.php
```

---

## Énumérations

### `Environment.php`

Représente les environnements applicatifs supportés de manière bornée et normalisée.

#### Cas d'usage

- Stabiliser les regroupements de logs
- Normaliser les données externes hostiles
- Garantir des valeurs cohérentes pour le domaine
- Utiliser comme composant du fingerprint

#### Cas énumérés

- `Production = 'prod'` - Environnement de production
- `Staging = 'staging'` - Environnement de préproduction/staging
- `Development = 'dev'` - Environnement de développement
- `Test = 'test'` - Environnement de test automatisé

#### Méthodes

##### `public static function fromExternal(mixed $value): self`

Crée un environnement depuis une valeur externe hostile.

**Paramètres :**

- `mixed $value` - Valeur externe non fiable

**Retour :** `self` - Toujours une instance valide

**Garanties :**

- Trim automatique
- Lowercase automatique
- Support des aliases (prod, production, live → Production)
- Fallback explicite vers Production
- Aucune exception levée

**Exemple :**

```php
$env = Environment::fromExternal('PRODUCTION');  // → Production
$env = Environment::fromExternal('dev');         // → Development
$env = Environment::fromExternal(123);           // → Production (fallback)
```

##### `public function isProduction(): bool`

Vérifie si l'environnement est la production.

**Retour :** `bool`

---

### `LogLevel.php`

Énumération des niveaux PSR-3 autorisés avec helpers métier.

#### Cas énumérés

- `DEBUG = 'debug'`
- `INFO = 'info'`
- `NOTICE = 'notice'`
- `WARNING = 'warning'`
- `ERROR = 'error'`
- `CRITICAL = 'critical'`
- `ALERT = 'alert'`
- `EMERGENCY = 'emergency'`

#### Méthodes

##### `public static function default(): self`

Retourne le niveau par défaut utilisé lors d'une ingestion incohérente.

**Retour :** `self` - Toujours `ERROR`

##### `public static function fromExternal(mixed $value): self`

Crée un niveau depuis une donnée externe hostile.

**Paramètres :**

- `mixed $value` - Valeur externe non fiable

**Retour :** `self` - Toujours un niveau valide

**Garanties :**

- Lowercase automatique
- Trim automatique
- Fallback sur ERROR

##### `public function isError(): bool`

Vérifie si le niveau représente une erreur.

**Retour :** `bool` - True si ERROR, CRITICAL, ALERT, ou EMERGENCY

##### `public function isCritical(): bool`

Vérifie si le niveau est critique.

**Retour :** `bool` - True si CRITICAL, ALERT, ou EMERGENCY

---

### `IngestionWarningType.php`

Énumération des types de warnings produits pendant l'ingestion.

#### Importance

Ces warnings **ne représentent PAS des erreurs fatales**, mais indiquent uniquement des corrections, normalisations ou données invalides récupérables.

#### Cas énumérés

- `MESSAGE_TRUNCATED = 'message_truncated'` - Message tronqué
- `DOMAIN_NORMALIZED = 'domain_normalized'` - Domaine corrigé
- `INVALID_LEVEL = 'invalid_level'` - Niveau invalide remplacé
- `INVALID_ENVIRONMENT = 'invalid_environment'` - Environnement invalide remplacé
- `INVALID_HTTP_STATUS = 'invalid_http_status'` - HTTP status invalide remplacé
- `INVALID_URI = 'invalid_uri'` - URI invalide remplacée
- `URI_TRUNCATED = 'uri_truncated'` - URI tronquée
- `INVALID_METHOD = 'invalid_method'` - Méthode HTTP invalide remplacée
- `METHOD_TRUNCATED = 'method_truncated'` - Méthode HTTP tronquée
- `USER_AGENT_TRUNCATED = 'user_agent_truncated'` - User-Agent tronqué
- `INVALID_IP = 'invalid_ip'` - IP invalide remplacée
- `INVALID_REQUEST_ID = 'invalid_request_id'` - RequestId invalide régénéré
- `FINGERPRINT_REGENERATED = 'fingerprint_regenerated'` - Fingerprint régénéré

---

## Entités du Domaine

### `LogEntry.php`

**SOURCE DE VÉRITÉ** du système - Représente un log immuable valide.

#### Responsabilités

- Encapsuler un log valide avec tous ses invariants métier
- Protéger les invariants du domaine
- Garantir une structure stable et immuable
- Empêcher les états incohérents

#### Invariants garantis

- ✓ Toujours valide (construit ou erreur)
- ✓ Immutable (readonly)
- ✓ Message normalisé et non vide
- ✓ Domaine normalisé et non vide
- ✓ Fingerprint toujours présent
- ✓ RequestId toujours présent
- ✓ Aucune dépendance Symfony métier
- ✓ Aucun état partiel

#### Constantes

- `MAX_MESSAGE_LENGTH = 1000` - Taille maximale du message
- `MAX_DOMAIN_LENGTH = 100` - Taille maximale du domaine

#### Propriétés

| Propriété           | Type                    | Immutable | Description                 |
| ------------------- | ----------------------- | --------- | --------------------------- |
| `externalId`        | string                  | ✓         | Identifiant UUID unique     |
| `message`           | string                  | ✓         | Message principal normalisé |
| `level`             | LogLevel                | ✓         | Niveau PSR-3                |
| `domain`            | string                  | ✓         | Domaine applicatif          |
| `environment`       | Environment             | ✓         | Environnement applicatif    |
| `httpStatus`        | HttpStatus              | ✓         | Code HTTP                   |
| `client`            | Client                  | ✓         | Client source               |
| `requestId`         | RequestId               | ✓         | Identifiant de corrélation  |
| `request`           | Request                 | ✓         | Requête HTTP                |
| `ipAddress`         | IpAddress               | ✓         | Adresse IP source           |
| `fingerprint`       | Fingerprint             | ✓         | Signature de regroupement   |
| `ingestionWarnings` | IngestionWarning[]      | ✓         | Anomalies corrigées         |
| `context`           | array                   | ✓         | Contexte libre              |
| `extra`             | array                   | ✓         | Données supplémentaires     |
| `createdAt`         | DateTimeImmutable       | ✓         | Date serveur                |
| `clientDate`        | DateTimeImmutable\|null | ✓         | Date client optionnelle     |

#### Méthodes

##### Constructor

```php
public function __construct(
    string $message,
    LogLevel $level,
    string $domain,
    Environment $environment,
    HttpStatus $httpStatus,
    Client $client,
    Request $request,
    IpAddress $ipAddress,
    Fingerprint $fingerprint,
    RequestId $requestId,
    array $ingestionWarnings = [],
    array $context = [],
    array $extra = [],
    ?DateTimeImmutable $clientDate = null,
    ?DateTimeImmutable $createdAt = null,
    ?string $externalId = null,
)
```

Crée une LogEntry valide.

**Validations :**

- Normalisation du message (trim)
- Normalisation du domaine (lowercase)
- Vérification du message non vide
- Vérification du domaine non vide
- Vérification des warnings

**Garanties :**

- Lève `InvalidLogEntryException` si invalide

##### `public function getExternalId(): string`

Retourne l'identifiant externe unique.

##### `public function getMessage(): string`

Retourne le message normalisé.

##### `public function getLevel(): LogLevel`

Retourne le niveau de log.

##### `public function getDomain(): string`

Retourne le domaine applicatif.

##### `public function getEnvironment(): Environment`

Retourne l'environnement.

##### `public function getHttpStatus(): HttpStatus`

Retourne le code HTTP.

##### `public function getClient(): Client`

Retourne le client source.

##### `public function getRequestId(): RequestId`

Retourne l'identifiant de corrélation.

##### `public function getRequest(): Request`

Retourne la requête HTTP.

##### `public function getIpAddress(): IpAddress`

Retourne l'adresse IP source.

##### `public function getFingerprint(): Fingerprint`

Retourne le fingerprint de regroupement.

##### `public function getIngestionWarnings(): array`

Retourne la liste des warnings d'ingestion.

**Retour :** `array<IngestionWarning>`

##### `public function getContext(): array`

Retourne le contexte libre.

**Retour :** `array<string, mixed>`

##### `public function getExtra(): array`

Retourne les données supplémentaires.

**Retour :** `array<string, mixed>`

##### `public function getCreatedAt(): DateTimeImmutable`

Retourne la date de création serveur.

##### `public function getClientDate(): ?DateTimeImmutable`

Retourne la date cliente optionnelle.

---

## Value Objects

Les value objects sont des structures immuables qui encapsulent et valident des données métier critiques.

### `Client.php`

Représente le client applicatif émetteur du log.

#### Invariants

- Jamais vide
- Lowercase
- Longueur bornée à 100 caractères
- Caractères sûrs uniquement ([a-z0-9._-])
- Immutable

#### Constantes

- `MAX_LENGTH = 100`
- `FALLBACK = 'unknown'`
- `PATTERN = '/^[a-z0-9._-]+$/'`

#### Méthodes

##### Constructor

```php
public function __construct(string $value)
```

**Lève :** `InvalidClientException` si invalide

##### `public static function fromExternal(mixed $value): self`

Crée un client depuis une donnée externe hostile.

**Règles :**

- Trim automatique
- Lowercase automatique
- Fallback sécurisé
- Aucune exception

**Exemple :**

```php
$client = Client::fromExternal('symfony-APP');  // → Client('symfony-app')
$client = Client::fromExternal(123);            // → Client('unknown')
```

##### `public function value(): string`

Retourne la valeur normalisée.

##### `public function isUnknown(): bool`

Vérifie si le client est le fallback système.

---

### `Fingerprint.php`

Représente un fingerprint stable, court et borné pour regrouper les logs similaires.

#### Format

- SHA1 tronqué à 16 caractères
- Hexadécimal uniquement ([a-f0-9])
- Format fixe et prédictible

#### Invariants

- Jamais vide
- Lowercase uniquement
- Format hexadécimal strict
- Longueur fixe (16 caractères)
- Immutable

#### Constantes

- `LENGTH = 16` - Longueur exacte
- `PATTERN = '/^[a-f0-9]{16}$/'`
- `FALLBACK = '0000000000000000'` - Fingerprint fallback

#### Méthodes

##### Constructor

```php
public function __construct(string $value)
```

**Lève :** `InvalidFingerprintException` si invalide

##### `public static function fromExternal(mixed $value): self`

Crée un fingerprint depuis une donnée externe hostile.

**Règles :**

- Lowercase automatique
- Trim automatique
- Fallback sécurisé
- Aucune exception

##### `public static function generate(string $base): self`

Génère un fingerprint depuis une base métier.

**Règles :**

- Trim
- Lowercase
- SHA1
- Truncation 16 caractères

**Exemple :**

```php
$fp = Fingerprint::generate('error|500|app|/api/users|prod');
// → Fingerprint('a1b2c3d4e5f6g7h8')
```

##### `public function value(): string`

Retourne la valeur hexadécimale.

##### `public function isFallback(): bool`

Vérifie si c'est le fallback d'ingestion.

##### `public function equals(self $other): bool`

Compare deux fingerprints.

##### `public function __toString(): string`

Représentation string stable.

---

### `HttpStatus.php`

Représente un code HTTP valide et borné.

#### Invariants

- Entier strict
- Compris entre 100 et 599
- Immutable

#### Constantes

- `FALLBACK_STATUS = 500` - Fallback ingestion
- `MAX_EXTERNAL_LENGTH = 10` - Limite de longueur externe

#### Méthodes

##### Constructor

```php
public function __construct(int $value)
```

**Lève :** `InvalidHttpStatusException` si invalide

##### `public static function fromExternal(mixed $value): self`

Crée un status HTTP depuis une valeur externe hostile.

**Règles :**

- Validation stricte
- Aucun cast implicite dangereux
- Fallback sécurisé (500)
- Aucune exception

**Exemple :**

```php
$status = HttpStatus::fromExternal(200);      // → HttpStatus(200)
$status = HttpStatus::fromExternal('404');    // → HttpStatus(404)
$status = HttpStatus::fromExternal('INVALID'); // → HttpStatus(500)
```

##### `public function value(): int`

Retourne la valeur brute.

##### `public function isInformational(): bool`

Vérifie si c'est un status informatif (100-199).

##### `public function isSuccess(): bool`

Vérifie si c'est un succès (200-299).

##### `public function isRedirection(): bool`

Vérifie si c'est une redirection (300-399).

##### `public function isClientError(): bool`

Vérifie si c'est une erreur client (400-499).

##### `public function isServerError(): bool`

Vérifie si c'est une erreur serveur (500-599).

---

### `IpAddress.php`

Représente une adresse IP valide et normalisée.

#### Support

- IPv4 : `192.168.1.1`
- IPv6 : `2001:0db8:85a3:0000:0000:8a2e:0370:7334`

#### Invariants

- IP toujours valide (IPv4 ou IPv6)
- String immutable
- Longueur bornée

#### Constantes

- `FALLBACK_IP = '0.0.0.0'` - Fallback ingestion
- `MAX_LENGTH = 45` - Longueur maximale IPv6

#### Méthodes

##### Constructor

```php
public function __construct(string $value)
```

**Lève :** `InvalidIpAddressException` si invalide

##### `public static function fromExternal(mixed $value): self`

Crée une IP depuis une donnée externe hostile.

**Règles :**

- Trim automatique
- Fallback sécurisé (0.0.0.0)
- Aucune exception
- Toujours une IP valide

**Exemple :**

```php
$ip = IpAddress::fromExternal('192.168.1.1');  // → IpAddress('192.168.1.1')
$ip = IpAddress::fromExternal('INVALID');      // → IpAddress('0.0.0.0')
```

##### `public function value(): string`

Retourne la valeur normalisée.

##### `public function isPrivate(): bool`

Vérifie si c'est une adresse IP privée.

---

### `Request.php`

Représente une requête HTTP normalisée et immuable.

#### Invariants

- Méthode HTTP valide (GET, POST, PUT, etc.)
- URI toujours valide
- User-Agent borné
- Données normalisées
- Immutable

#### Constantes

- `ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']`
- `MAX_METHOD_LENGTH = 10`
- `MAX_USER_AGENT_LENGTH = 500`

#### Propriétés

- `uri`: Uri - URI normalisée
- `method`: string - Méthode HTTP normalisée
- `userAgent`: string - User-Agent normalisé

#### Méthodes

##### Constructor

```php
public function __construct(
    Uri $uri,
    string $method = 'GET',
    string $userAgent = '',
)
```

**Lève :** `InvalidRequestException` si invalide

##### `public static function fromExternal(array $data): self`

Crée une Request depuis des données externes hostiles.

**Règles :**

- Aucune exception
- Fallback sécurisé
- Normalisation agressive

##### `public function getUri(): Uri`

Retourne l'URI normalisée.

##### `public function getMethod(): string`

Retourne la méthode HTTP normalisée.

##### `public function getUserAgent(): string`

Retourne le User-Agent normalisé.

---

### `RequestId.php`

Représente un identifiant de corrélation de requête immuable.

#### Format attendu

- Longueur : 3 à 100 caractères
- Caractères autorisés : `[a-z0-9-_]`
- Exemples : `req_8f5c1a`, `api-request-123`, `trace_abc_789`

#### Invariants

- Toujours non vide
- Trim automatique
- Lowercase automatique
- Caractères sûrs uniquement
- Taille bornée
- Immutable

#### Constantes

- `MIN_LENGTH = 3`
- `MAX_LENGTH = 100`
- `REGEX = '/^[a-z0-9\-_]+$/'`

#### Méthodes

##### Constructor

```php
public function __construct(string $value)
```

**Lève :** `InvalidRequestIdException` si invalide

##### `public static function generate(): self`

Génère automatiquement un requestId robuste.

**Format généré :** `req_` + 16 caractères hexadécimaux aléatoires

**Exemple :**

```php
$id = RequestId::generate();  // → RequestId('req_a1b2c3d4e5f6g7h8')
```

##### `public static function fromExternal(mixed $value): self`

Crée un RequestId depuis une donnée externe hostile.

**Règles :**

- Aucune exception
- Fallback sécurisé
- Génération automatique si nécessaire

##### `public function value(): string`

Retourne la valeur normalisée.

---

### `Uri.php`

Représente une URI HTTP normalisée et sécurisée.

#### Invariants

- Toujours un chemin valide
- Toujours normalisé
- Jamais vide
- Longueur bornée (2048)
- Sans query string
- Immutable

#### Constantes

- `MAX_LENGTH = 2048` - Taille maximale
- `FALLBACK_URI = '/'` - Fallback ingestion

#### Méthodes

##### Constructor

```php
public function __construct(string $value)
```

**Lève :** `InvalidUriException` si invalide

##### `public static function fromExternal(mixed $value): self`

Crée une URI depuis une donnée externe hostile.

**Règles :**

- Fallback "/"
- Trim automatique
- Suppression query string (?...)
- Suppression fragment (#...)
- Nettoyage caractères invalides
- Aucune exception

**Exemple :**

```php
$uri = Uri::fromExternal('/api/users?page=1#top');  // → Uri('/api/users')
$uri = Uri::fromExternal('INVALID');               // → Uri('/')
```

##### `public function value(): string`

Retourne la valeur normalisée sans query string.

##### `public function isRoot(): bool`

Vérifie si l'URI représente la racine.

---

### `IngestionWarning.php`

Enregistre les anomalies d'ingestion (corrections, normalisations).

#### Importance

Un warning d'ingestion :

- n'est **PAS** une erreur fatale
- ne doit **jamais** casser l'ingestion
- doit rester sérialisable et borné
- doit tracer la correction appliquée

#### Propriétés

- `field`: string - Champ concerné
- `type`: IngestionWarningType - Type du warning
- `original`: mixed - Valeur originale reçue
- `fallback`: mixed - Valeur fallback utilisée

#### Constantes

- `MAX_VALUE_LENGTH = 500` - Limite de sérialisation

#### Méthodes

##### Constructor

```php
public function __construct(
    string $field,
    IngestionWarningType $type,
    mixed $original = null,
    mixed $fallback = null,
)
```

##### `public function field(): string`

Retourne le champ concerné.

##### `public function type(): IngestionWarningType`

Retourne le type de warning.

##### `public function original(): mixed`

Retourne la valeur originale reçue.

##### `public function fallback(): mixed`

Retourne la valeur fallback utilisée.

##### `public function toArray(): array`

Retourne une structure sérialisable stable.

**Retour :** `array<string, mixed>`

**Exemple :**

```php
[
    'field' => 'message',
    'type' => 'message_truncated',
    'original' => 1234,
    'fallback' => 'unknown error',
]
```

##### `public function jsonSerialize(): mixed`

Sérialise pour JSON.

---

## Application Layer

### `LogEntryFactory.php`

Factory responsable de la **création robuste des LogEntry** depuis des payloads externes hostiles.

#### Responsabilités

- Assembler les ValueObjects
- Protéger le domaine des payloads hostiles
- Garantir une création robuste sans crash
- Fournir un point d'entrée unique
- Tracer les corrections ingestion

#### Objectifs

- ✓ Robustesse (zéro crash ingestion)
- ✓ Prédictibilité
- ✓ Stabilité
- ✓ Aucune perte de données

#### Constantes

- `MAX_MESSAGE_LENGTH = 1000`
- `MAX_DOMAIN_LENGTH = 100`
- `MAX_METHOD_LENGTH = 10`
- `MAX_USER_AGENT_LENGTH = 500`

#### Méthodes

##### `public function create(array $payload): LogEntry`

Crée un LogEntry depuis un payload externe.

**Paramètres :**

- `array<string, mixed> $payload` - Payload brut non fiable

**Retour :** `LogEntry` - Instance valide et normalisée

**Garantie :** Ne lève **jamais d'exception** (fallback robuste)

**Processus :**

1. Extraction et normalisation de chaque champ
2. Traçage des anomalies détectées
3. Application des fallbacks sécurisés
4. Création de la LogEntry finale

**Exemple de payload :**

```php
$payload = [
    'message' => '  User login  ',
    'level' => 'info',
    'domain' => 'Auth-System',
    'env' => 'production',
    'httpStatus' => 200,
    'client' => 'mobile-app',
    'requestId' => 'req_abc123',
    'context' => ['user_id' => 42],
    'extra' => ['timestamp' => 1234567890],
];

$logEntry = $factory->create($payload);
```

##### `private function createId(array $payload): string`

Crée un UUID stable.

**Logique :**

- Accepte l'UUID du payload s'il est valide
- Génère un nouvel UUID v7 sinon

##### `private function createMessage(array $payload, array &$warnings): string`

Crée un message robuste.

**Logique :**

- Trim
- Validation type string
- Troncature à MAX_MESSAGE_LENGTH
- Traçage des anomalies

##### `private function createLevel(array $payload, array &$warnings): LogLevel`

Crée un niveau robuste.

**Logique :**

- Normalisation (lowercase, trim)
- Validation contre énumération
- Fallback sur ERROR

##### `private function createEnvironment(array $payload, array &$warnings): Environment`

Crée un environnement robuste.

**Logique :**

- Utilise `Environment::fromExternal()`
- Fallback sur Production

##### `private function createHttpStatus(array $payload, array &$warnings): HttpStatus`

Crée un status HTTP robuste.

**Logique :**

- Utilise `HttpStatus::fromExternal()`
- Fallback sur 500

##### `private function createClient(array $payload, array &$warnings): Client`

Crée un client robuste.

**Logique :**

- Utilise `Client::fromExternal()`
- Fallback sur 'unknown'

##### `private function createRequest(array $payload, array &$warnings): Request`

Crée une requête HTTP robuste.

**Logique :**

- Extraction de request.uri, request.method, request.userAgent
- Normalisation de chaque composant
- Construction Request validée

##### `private function createIpAddress(array $payload, array &$warnings): IpAddress`

Crée une IP robuste.

**Logique :**

- Utilise `IpAddress::fromExternal()`
- Fallback sur '0.0.0.0'

##### `private function createFingerprint(array $payload, array &$warnings): Fingerprint`

Crée un fingerprint robuste.

**Logique :**

- Utilise `FingerprintGenerator`
- Fallback sur '0000000000000000'

##### `private function createRequestId(array $payload, array &$warnings): RequestId`

Crée un requestId robuste.

**Logique :**

- Valide le requestId du payload
- Génère un nouveau sinon

---

### `LogEntryFactoryInterface.php`

Contrat public pour les factories de création de LogEntry.

```php
public function create(array $payload): LogEntry;
```

---

### `FingerprintGenerator.php`

Génère des fingerprints **stables, déterministes et prédictibles** pour regrouper les logs similaires.

#### Responsabilités

- Stabiliser le regroupement des erreurs
- Produire des signatures déterministes
- Ignorer les données volatiles (timestamps, IPs, etc.)
- Protéger contre les variations externes

#### Format

```
level|httpStatus|domain|uri|env
```

**Exemple :** `error|500|payment-service|/api/checkout|prod`

#### Invariants

- Lowercase normalisé
- Trim appliqué
- Query string ignorée (URI uniquement)
- Hash SHA1 tronqué à 16 caractères

#### Constantes

- `LENGTH = 16` - Longueur finale du fingerprint

#### Méthodes

##### `public function generate(LogLevel $level, HttpStatus $httpStatus, string $domain, Uri $uri, Environment $environment): Fingerprint`

Génère un fingerprint stable.

**Paramètres :**

- `LogLevel $level` - Niveau du log
- `HttpStatus $httpStatus` - Code HTTP
- `string $domain` - Domaine applicatif
- `Uri $uri` - URI de la requête
- `Environment $environment` - Environnement

**Retour :** `Fingerprint` - Signature stable

**Exemple :**

```php
$fingerprint = $generator->generate(
    LogLevel::ERROR,
    new HttpStatus(500),
    'payment',
    new Uri('/api/checkout'),
    Environment::Production,
);
// → Fingerprint('a1b2c3d4e5f6g7h8')
```

##### `private function normalize(string $value): string`

Normalise une valeur utilisée dans le fingerprint.

**Logique :**

- Lowercase
- Trim

---

### `LogNormalizerConfig.php`

Centralise les **constantes techniques** du système de normalisation.

#### Responsabilités

- Définir les limites mémoire
- Définir les limites payload
- Définir les bornes techniques
- Définir les clés sensibles
- Centraliser les messages système

#### Constantes

| Constante                 | Valeur                               | Description                            |
| ------------------------- | ------------------------------------ | -------------------------------------- |
| `MAX_STRING_LENGTH`       | 1000                                 | Taille maximale des chaînes            |
| `MAX_URI_LENGTH`          | 2000                                 | Taille maximale des URI                |
| `MAX_ARRAY_ITEMS`         | 50                                   | Nombre maximal d'éléments par tableau  |
| `MAX_DEPTH`               | 5                                    | Profondeur maximale de récursion       |
| `MAX_TRACE_FRAMES`        | 50                                   | Nombre maximal de frames de stacktrace |
| `MAX_REQUEST_ID_LENGTH`   | 100                                  | Longueur maximale du requestId         |
| `SENSITIVE_KEYS`          | ['password', 'token', 'secret', ...] | Clés sensibles à filtrer               |
| `MAX_DEPTH_MESSAGE`       | '[MAX_DEPTH]'                        | Message profondeur max atteinte        |
| `FILTERED_MESSAGE`        | '[FILTERED]'                         | Message données filtrées               |
| `INVALID_TYPE_MESSAGE`    | '[INVALID_TYPE]'                     | Message types invalides                |
| `OBJECT_MESSAGE_TEMPLATE` | '[OBJECT %s]'                        | Template objet non sérialisable        |
| `DEFAULT_MESSAGE`         | 'Unknown error'                      | Message fallback                       |
| `DEFAULT_DOMAIN`          | 'unknown'                            | Domaine fallback                       |
| `DEFAULT_ENV`             | 'prod'                               | Environnement fallback                 |
| `DEFAULT_CLIENT`          | 'unknown-client'                     | Client fallback                        |
| `DEFAULT_HTTP_STATUS`     | 500                                  | Status HTTP fallback                   |
| `DEFAULT_URI`             | '/'                                  | URI fallback                           |
| `REQUEST_ID_PREFIX`       | 'req\_'                              | Préfixe requestId généré               |

---

### `LogPayloadNormalizer.php`

Normalise un payload de log externe **hostile** en structure **sûre, bornée et prédictible**.

#### Responsabilités

- Appliquer les fallbacks
- Générer les champs système
- Filtrer les données sensibles
- Limiter profondeur/taille
- Stabiliser les données
- Sécuriser les objets
- Protéger UTF8
- Protéger mémoire et récursion
- Ne jamais provoquer d'erreur fatale

#### Propriétés

- `clock`: ClockInterface - Horloge injectable pour tests

#### Méthodes

##### Constructor

```php
public function __construct(
    private readonly ClockInterface $clock,
)
```

Injecte une horloge pour tests déterministes.

##### `public function normalize(mixed $payload): array`

Normalise un payload externe hostile.

**Paramètres :**

- `mixed $payload` - Payload brut non fiable (peut ne pas être un array)

**Retour :** `array<string, mixed>` - Payload normalisé valide

**Garanties :**

- Toujours retourner un array valide
- Jamais d'erreur fatale
- Toujours des données cohérentes
- Toujours des bornes mémoire

**Structure retournée :**

```php
[
    'message' => string,           // Message normalisé
    'level' => string,             // Niveau PSR-3
    'domain' => string,            // Domaine applicatif
    'env' => string,               // Environnement
    'httpStatus' => int,           // Code HTTP
    'client' => string,            // Client source
    'requestId' => string,         // Identifiant corrélation
    'externalId' => string,        // UUID client
    'createdAt' => string,         // Timestamp ISO8601 serveur
    'clientDate' => ?string,       // Timestamp ISO8601 client
    'fingerprint' => string,       // Signature regroupement
    'context' => array,            // Contexte libre normalisé
    'extra' => array,              // Données supplémentaires normalisées
    'tags' => array,               // Tags normalisés
    'exception' => array,          // Exception normalisée
    'request' => array,            // Requête normalisée
    'server' => array,             // Données serveur normalisées
    'runtime' => array,            // Données runtime normalisées
    'user' => array,               // Données utilisateur normalisées
]
```

##### `private function normalizeArray(mixed $value, int $depth): mixed`

Normalise récursivement une structure.

**Paramètres :**

- `mixed $value` - Valeur à normaliser (peut être array, objet, scalaire)
- `int $depth` - Profondeur actuelle de récursion

**Retour :** `mixed` - Valeur normalisée

**Garanties :**

- Profondeur bornée
- Mémoire bornée
- Objets sécurisés
- Données sensibles filtrées

**Logique :**

1. Vérifier limite profondeur → retourner `[MAX_DEPTH]`
2. Traiter les objets → normaliser comme valeur
3. Traiter les scalaires → normaliser comme scalaire
4. Traiter les arrays → itérer avec limite d'éléments

---

## Infrastructure Layer

### `FileQueueWriter.php`

Écrit des logs JSON **atomiquement** dans la queue disque.

#### Responsabilités

- 1 log = 1 fichier JSON
- Écriture atomique (fichier temporaire + rename)
- Robustesse filesystem
- Isolation des fichiers partiels
- Aucune perte silencieuse

#### Garanties

- ✓ Aucun fichier partiellement visible
- ✓ Aucun JSON tronqué
- ✓ Écriture filesystem safe
- ✓ Nettoyage des fichiers temporaires

#### Constantes

- `TEMP_EXTENSION = '.tmp'` - Extension fichiers temporaires

#### Dépendances injectées

- `QueueDirectoryManager $directoryManager` - Gestion répertoires
- `QueueFilenameGenerator $filenameGenerator` - Génération noms

#### Méthodes

##### Constructor

```php
public function __construct(
    private readonly QueueDirectoryManager $directoryManager,
    private readonly QueueFilenameGenerator $filenameGenerator,
)
```

##### `public function write(string $directory, string $jsonPayload): string`

Écrit un payload JSON dans la queue disque atomiquement.

**Paramètres :**

- `string $directory` - Répertoire queue destination
- `string $jsonPayload` - Payload JSON valide

**Retour :** `string` - Chemin final du fichier écrit

**Lève :** `QueueWriteException` si erreur

**Processus :**

1. Valider le payload JSON
2. S'assurer que le répertoire existe
3. Générer un nom de fichier unique
4. Écrire temporairement dans `fichier.tmp`
5. Renommer atomiquement vers le fichier final
6. Vérifier l'existence du fichier final
7. Nettoyer les fichiers temporaires en cas d'erreur

**Exemple :**

```php
$path = $writer->write(
    '/var/queue/logs',
    json_encode(['message' => 'Error', 'level' => 'error'])
);
// → '/var/queue/logs/20260510_013015_654321_ab12cd34ef56.json'
```

##### `private function writeTemporaryFile(string $temporaryPath, string $payload): void`

Écrit le fichier temporaire de manière sécurisée.

**Lève :** `QueueWriteException` si erreur

##### `private function moveTemporaryFile(string $temporaryPath, string $finalPath): void`

Déplace atomiquement le fichier temporaire.

**Lève :** `QueueWriteException` si erreur

##### `private function assertFinalFileExists(string $finalPath): void`

Vérifie que le fichier final existe réellement.

**Lève :** `QueueWriteException` si absence

---

### `QueueDirectoryManager.php`

Garantit qu'un dossier de queue est **réellement utilisable** de manière robuste.

#### Responsabilités

- Création sécurisée des répertoires
- Validation filesystem
- Validation permissions
- Robustesse race conditions
- Validation chemins

#### Garanties

- ✓ Répertoire existant
- ✓ Répertoire writable
- ✓ Répertoire readable
- ✓ Répertoire réellement utilisable

#### Constantes

- `DIRECTORY_PERMISSIONS = 0755` - Permissions répertoires créés

#### Méthodes

##### `public function ensureDirectoryExists(string $directory): void`

Garantit qu'un répertoire existe et est utilisable.

**Paramètres :**

- `string $directory` - Chemin du répertoire

**Lève :** `QueueDirectoryException` si non utilisable

**Logique :**

1. Valider le chemin
2. Si existe → valider qu'il soit utilisable
3. Si n'existe pas → créer et valider

**Idempotent :** Appels multiples sans effet

**Exemple :**

```php
$manager->ensureDirectoryExists('/var/queue/logs');
// Crée le répertoire s'il n'existe pas
// Valide qu'il soit readable/writable
```

##### `private function createDirectory(string $directory): void`

Crée un répertoire de manière sécurisée.

**Lève :** `QueueDirectoryException` si impossible

**Logique :**

- `mkdir()` avec permissions 0755 et récursion
- Gère les race conditions (un autre process crée le dossier en parallèle)

##### `private function assertDirectoryUsable(string $directory): void`

Vérifie qu'un répertoire est réellement exploitable.

**Validations :**

- Existe
- Est un répertoire (pas un fichier)
- N'est pas un lien symbolique
- Est readable
- Est writable

**Lève :** `QueueDirectoryException` si problème

---

### `QueueFilenameGenerator.php`

Génère des **noms de fichiers de queue robustes, uniques et triables** chronologiquement.

#### Format généré

```
YYYYMMDD_HHMMSS_microseconds_random.json
```

**Exemple :** `20260510_013015_654321_ab12cd34ef56.json`

#### Invariants

- Sort chronologiquement naturellement
- Compatible filesystem
- Aucun caractère dangereux
- Collisions extrêmement improbables
- Validation stricte du format
- Robustesse production mutualisée

#### Constantes

- `FILENAME_PATTERN` - Regex stricte de validation

#### Dépendances injectées

- `ClockInterface $clock` - Horloge injectable

#### Méthodes

##### Constructor

```php
public function __construct(
    private readonly ClockInterface $clock,
)
```

Injecte une horloge pour tests déterministes.

##### `public function generate(): string`

Génère un nom de fichier de queue unique.

**Retour :** `string` - Nom de fichier formaté

**Lève :** `QueueFilenameException` si impossible

**Composants :**

1. `YYYYMMDD` - Date au format compact
2. `HHMMSS` - Temps au format compact
3. `microseconds` - 6 chiffres de microsecondes
4. `random` - 12 caractères hexadécimaux aléatoires sécurisés
5. `.json` - Extension

**Garanties :**

- Tri chronologique
- Unicité (probabilité collision ~1 en 281 trillions)

**Exemple :**

```php
$filename = $generator->generate();
// → '20260510_013015_654321_ab12cd34ef56.json'
```

##### `private function generateRandomSegment(): string`

Génère un segment aléatoire sécurisé (12 caractères hexadécimaux).

**Lève :** `QueueFilenameException` si entropie insuffisante

##### `private function assertValidFilename(string $filename): void`

Vérifie que le filename généré respecte strictement le format.

**Validations :**

- Non vide
- Longueur ≤ 255 (limite filesystem)
- Respecte la regex du format

**Lève :** `QueueFilenameException` si invalide

---

## Exceptions personnalisées

### Domain Layer

Toutes les exceptions domaine étendent une classe de base commune.

- **`InvalidLogEntryException`** - LogEntry invalide
- **`InvalidClientException`** - Client invalide
- **`InvalidFingerprintException`** - Fingerprint invalide
- **`InvalidHttpStatusException`** - HTTP status invalide
- **`InvalidIpAddressException`** - Adresse IP invalide
- **`InvalidRequestException`** - Requête HTTP invalide
- **`InvalidRequestIdException`** - RequestId invalide
- **`InvalidUriException`** - URI invalide

### Infrastructure Layer

- **`QueueDirectoryException`** - Problème répertoire queue
- **`QueueFilenameException`** - Impossible générer filename
- **`QueueWriteException`** - Impossible écrire fichier queue

---

## Flux de traitement complet

```
Payload externe (non fiable)
        ↓
LogPayloadNormalizer::normalize()
        ↓
Payload normalisé (structure sûre)
        ↓
LogEntryFactory::create()
        ↓
- Création de chaque ValueObject
- Traçage des anomalies → IngestionWarning[]
- Génération du Fingerprint
        ↓
LogEntry (immuable, valide)
        ↓
FileQueueWriter::write()
        ↓
Fichier JSON atomique dans queue
```

---

## Bonnes pratiques d'utilisation

### 1. Toujours utiliser les factories

```php
// ✓ BON - Utiliser la factory
$logEntry = $factory->create([
    'message' => 'User login',
    'level' => 'info',
    'domain' => 'auth',
]);

// ✗ MAUVAIS - Constructor direct risqué
$logEntry = new LogEntry(...); // Peut crash si données invalides
```

### 2. Exploiter les IngestionWarnings

```php
$logEntry = $factory->create($payload);

foreach ($logEntry->getIngestionWarnings() as $warning) {
    // Tracer les anomalies corrigées
    logger()->warning('Ingestion correction', [
        'field' => $warning->field(),
        'type' => $warning->type()->value,
        'original' => $warning->original(),
        'fallback' => $warning->fallback(),
    ]);
}
```

### 3. Utiliser les helpers métier

```php
$logEntry = $factory->create($payload);

if ($logEntry->getLevel()->isCritical()) {
    // Escalade immédiate
    alert('Critical error: ' . $logEntry->getMessage());
}

if ($logEntry->getHttpStatus()->isServerError()) {
    // Indicateur problème serveur
}
```

### 4. Immutabilité garantie

```php
$logEntry = $factory->create($payload);

// ✓ Impossible de modifier
$logEntry->message = 'Other'; // TypeError: Cannot modify readonly property

// ✓ Récupérer les valeurs
$message = $logEntry->getMessage();
```

---

## Évolution et stabilité

Le module Log est conçu pour **l'évolution progressive** :

### Couches stables

- **Énumérations** : Très stables, ajout uniquement
- **ValueObjects** : Stables, modification très rare
- **Domain Entity** : Stable, évolution via extension
- **Factory** : Stable, évolution backward-compatible

### Couches évolutives

- **Normalizer** : Règles métier peuvent évoluer
- **FingerprintGenerator** : Algorithme peut s'améliorer
- **Infrastructure** : Implémentations remplaçables

---

## Dépannage

### Tous mes logs obtiennent un fingerprint fallback

**Cause :** Erreur lors de la génération du fingerprint  
**Solution :** Vérifier les logs d'ingestion warnings

### Payload rejeté avec exception

**Cause :** Utilisation direct du constructor au lieu de la factory  
**Solution :** Utiliser `LogEntryFactory::create()`

### Données sensibles visibles

**Cause :** Clés sensibles non filtrées  
**Solution :** Vérifier `LogNormalizerConfig::SENSITIVE_KEYS`
