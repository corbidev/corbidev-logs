# PROJECT: MUTUALIZED LOGGING PLATFORM

# CONTEXTE

- PHP 8.4
- Symfony 8
- Hébergement mutualisé (IONOS)
- Système de logs mutualisé type GlitchTip/Sentry simplifié
- Compatible PSR-3
- Pipeline synchrone robuste
- Architecture simple et explicite

---

# OBJECTIFS

Construire un système de logs :

- robuste
- prévisible
- sécurisé
- maintenable
- tolérant aux erreurs
- optimisé pour mutualisé

Le système ne doit jamais casser à cause des données reçues.

---

# PHILOSOPHIE

- un log imparfait vaut mieux qu’un log perdu
- aucune donnée externe n’est fiable
- aucune logique cachée
- flux unidirectionnel uniquement
- une responsabilité par classe
- moins d’abstraction = meilleure maintenabilité
- WRITE et READ sont des problèmes différents

---

# GLOBAL_RULES

- TOUJOURS écrire les tests AVANT l’implémentation
- SI CE N’EST PAS TESTÉ → ÇA N’EXISTE PAS
- architecture simple uniquement
- aucun composant magique
- aucun comportement implicite
- nommage explicite obligatoire
- privilégier les objets métier explicites
- éviter les services génériques
- éviter les classes fourre-tout

---

# FORBIDDEN

- API Platform
- Messenger
- Async workers
- RabbitMQ
- Redis obligatoire
- CQRS complexe
- Event Bus
- Event Sourcing
- Microservices
- React si non justifié
- Vue si non justifié
- SPA complexes non nécessaires
- ServiceLocator
- dossiers Utils/
- dossiers Helper/
- logique métier dans des listeners Doctrine
- logique métier dans Shared/

---

# STACK_BACKEND

- Symfony 8
- Doctrine ORM
- Monolog
- Symfony HttpClient
- Symfony Console
- MySQL / MariaDB

---

# STACK_FRONTEND

## Stack recommandée

- Twig
- HTMX
- Alpine.js
- JavaScript vanilla

## Optionnel

- Tailwind CSS
- Vite

---

# FRONTEND_RULES

- HTML serveur = source de vérité
- privilégier HTMX pour les interactions
- privilégier Alpine.js pour les micro-interactions
- JavaScript vanilla prioritaire
- éviter les frameworks frontend lourds
- éviter les SPA complexes
- éviter les états frontend dupliqués
- privilégier le progressive enhancement

---

# HTTP_RULES

- routes API → JSON uniquement
- routes Front → HTML uniquement
- jamais de contrôleur hybride
- jamais de mélange HTML/JSON
- séparation stricte API/UI

---

# ARCHITECTURE

```txt
src/

- Ingestion/
  - Http/
    - Api/
  - Application/
  - Domain/
  - Infrastructure/

- Log/
  - Domain/
  - Application/
  - Infrastructure/
  - ReadModel/

- Queue/
  - Application/
  - Infrastructure/

- Persistence/
  - Application/
  - Infrastructure/

- Search/
  - Application/
  - Infrastructure/
  - Http/
    - Api/

- Dashboard/
  - Controller/
  - Twig/

- Project/
  - Domain/
  - Application/
  - Infrastructure/

- ApiToken/
  - Domain/
  - Application/
  - Infrastructure/

- Shared/
  - Clock/
  - Doctrine/
  - Http/
  - Json/
  - Logging/
  - Security/
  - ValueObject/
  - Exception/
```

---

# ARCHITECTURE_RULES

## Domain

Contient uniquement :

- règles métier
- invariants
- objets métier
- validation métier

Le Domain ne dépend jamais de Symfony.

---

## Application

Responsable :

- orchestration
- use cases
- coordination des composants

Aucune logique technique lourde.

---

## Infrastructure

Responsable :

- DB
- fichiers
- HTTP
- queue
- filesystem
- Doctrine
- mail
- persistence

---

## Shared

UNIQUEMENT technique transversal.

---

# SHARED_RULES

## Autorisé

- Clock
- Json
- Exceptions techniques
- sécurité technique
- hashing
- logging technique
- ValueObjects techniques
- helpers techniques strictement purs

## Interdit

- logique métier
- règles de logs
- logique applicative
- use cases

---

# FLOW_RULE

```txt
HTTP
→ Validation minimale
→ Queue disque
→ Réponse HTTP rapide

CRON
→ Lecture queue
→ Normalisation
→ Construction LogEntry
→ Persistence DB
→ Indexation légère
```

---

# DOMAIN_RULES

- LogEntry est immutable
- aucun LogEntry incomplet
- fingerprint toujours recalculé serveur
- aucune confiance au payload client
- aucun objet métier invalide
- toute donnée externe doit être normalisée

---

# LOGENTRY_CONTRACT

## Champs obligatoires stricts

- message
- level
- domain
- env
- httpStatus
- client

## Champs obligatoires auto-corrigés

- externalId
- createdAt
- clientDate
- uri
- ip

## Champs optionnels

- userId
- context

---

# FINGERPRINT_RULES

## Construction

```txt
level|httpStatus|domain|uri|env
```

## Règles

- lowercase
- trim
- suppression query string
- sha1 tronqué 16 caractères
- toujours recalculé serveur

---

# NORMALIZER_RULES

## Objectif

Empêcher tout crash JSON.

## Limites

- profondeur max : 5
- éléments max : 50
- string max : 1000 caractères

## Types interdits

- resource
- recursion infinie
- objets non sérialisables

## Types spéciaux

- object → "[object Class]"
- recursion → "[circular]"
- resource → "[resource]"

## Données sensibles filtrées

- password
- pwd
- token
- authorization
- cookie

→ "[FILTERED]"

---

# INGESTION_RULES

- endpoint ultra léger
- validation minimale synchrone
- aucune persistence DB directe
- réponse HTTP rapide obligatoire
- aucune hydratation Doctrine complexe
- aucune logique métier lourde
- payload limité
- protection anti flood obligatoire

---

# QUEUE_RULES

- 1 log = 1 fichier
- écriture atomique obligatoire
- aucun lock complexe
- aucun worker permanent
- retry limité
- fichier corrompu isolé
- queue indépendante de la DB

---

# QUEUE_FLOW

```txt
queue/
→ success → delete
→ fail → .error
→ retry
→ failed/
```

---

# CRON_RULES

- cron Symfony uniquement
- traitement batch simple
- aucun daemon permanent
- aucun process infini
- mémoire bornée
- traitement idempotent

---

# PERSISTENCE_RULES

- persistence indépendante
- insert batch favorisé
- aucune logique métier SQL
- aucune cascade Doctrine complexe
- aucun listener Doctrine métier
- aucune hydratation massive

---

# DATABASE_RULES

## Philosophie

La DB est un append-only store optimisé pour :

- écrire vite
- lire simplement
- purger facilement

---

## Autorisé

- JSON pour données variables
- index ciblés
- SQL simple
- pagination stricte

---

## Interdit

- triggers complexes
- procédures stockées métier
- relations profondes
- héritage Doctrine complexe
- SELECT non borné

---

# DATABASE_STRUCTURE

## projects

```txt
id
name
slug
retention_days
created_at
```

---

## api_tokens

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

## logs

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

# SEARCH_RULES

- pagination obligatoire
- LIMIT obligatoire
- aucun SELECT *
- recherche bornée
- index obligatoires sur filtres fréquents
- lecture optimisée séparée mentalement du write

---

# AUTH_RULES

## API Tokens

- tokens opaques uniquement
- stockage hashé obligatoire
- jamais de stockage du token brut
- révocation immédiate possible
- expiration obligatoire
- comparaison temps constant obligatoire

## Interdit

- JWT pour ingestion logs
- auth complexe
- permissions dynamiques lourdes

---

# API_RESPONSE_RULES

## Succès

```json
{
  "success": true,
  "data": {}
}
```

## Erreur

```json
{
  "success": false,
  "error": {}
}
```

---

# LOGGING_RULES

- logs structurés uniquement
- request_id obligatoire
- aucune donnée sensible
- jamais de boucle de logs
- aucun crash du logger

---

# PERFORMANCE_RULES

- éviter toute abstraction inutile
- éviter réflexion runtime
- éviter services génériques
- éviter allocations inutiles
- SQL explicite privilégié
- aucune requête non bornée
- priorité aux écritures rapides

---

# TESTING_RULES

## Priorités

1. Domain
2. Factory
3. Normalizer
4. Queue
5. Persistence
6. Search

---

## Cas critiques obligatoires

- récursion
- JSON invalide
- payload corrompu
- disque plein
- queue corrompue
- DB indisponible
- données sensibles
- timestamps invalides

---

# PHPDOC_RULES

- PHPDoc obligatoire en français
- expliquer le POURQUOI
- aucun PHPDoc vide
- aucun commentaire inutile
- documenter les invariants métier

---

# CODE_STYLE_RULES

- méthodes courtes
- responsabilités uniques
- dépendances explicites
- éviter les méthodes statiques globales
- éviter les booléens magiques
- éviter les tableaux non typés dans le Domain

---

# GOLDEN_RULE

```txt
SI CE N’EST PAS TESTÉ → ÇA N’EXISTE PAS
```
