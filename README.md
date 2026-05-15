# Mutualized Logging Platform

Plateforme de logs mutualisée conçue pour Symfony 8 et PHP 8.4, avec une priorité forte sur la robustesse d'écriture, la simplicité et la compatibilité mutualisée.

## Position du projet

Le README décrit la vision globale du produit, mais aussi l'état actuel du dépôt.

Ce qui est déjà en place dans le code :

- normalisation des payloads hostiles
- création de `LogEntry`
- écriture de queue disque
- persistence SQL via Doctrine DBAL

Ce qui reste encore à stabiliser ou à compléter :

- la surface HTTP finale d'ingestion
- le dashboard de lecture
- la recherche bornée
- l'UX frontend
- la clarification définitive de la stratégie d'authentification

## Principes

1. robustesse
2. simplicité
3. prédictibilité
4. maintenabilité
5. performances d'écriture

Le système suit ces règles :

- un log imparfait vaut mieux qu'un log perdu
- toutes les données externes sont hostiles
- aucun comportement implicite
- aucun composant magique
- flux simples uniquement
- responsabilités séparées

## Architecture cible

Le projet est organisé autour de deux axes.

### Write side

Responsable de :

- ingestion
- validation minimale
- queue disque
- persistence SQL

Objectif : écrire vite, sans perte, avec un pipeline stable.

### Read side

Responsable de :

- dashboard
- recherche
- pagination
- filtres

Objectif : lire simplement, avec des requêtes bornées et des index ciblés.

## Stack technique

### Backend

- PHP 8.4
- Symfony 8
- Doctrine ORM / DBAL
- MySQL / MariaDB
- Monolog

### Frontend

- Twig
- JavaScript vanilla

### Cible ou option selon l'avancement

- HTMX
- Alpine.js
- Tailwind CSS
- Vite

## Surfaces HTTP

### API

La surface d'ingestion est prévue sous `/api/*` et doit rester JSON-only.

### Dashboard

La surface de lecture est prévue sous `/dashboard/*` et doit rester HTML-only.

### Authentification

La cible fonctionnelle reste des tokens opaques hashés, révocables et expirables.

Le dépôt contient encore une dépendance JWT, donc le README ne doit pas prétendre que JWT est déjà entièrement exclu sans nuance. La situation doit être considérée comme une transition ou un héritage à clarifier.

## Contrat d'ingestion

Le contrat officiel du payload JSON est documenté dans [README_INGESTION_CONTRACT_JSON.md](README_INGESTION_CONTRACT_JSON.md).

Exemple de forme attendue pour l'ingestion :

```txt
POST /api/logs
Authorization: Bearer lgp_xxxxxxxxx
Content-Type: application/json
```

Réponse attendue côté API :

```json
{
  "success": true,
  "data": {
    "received": 1
  }
}
```

## Briques déjà présentes

La base réelle du write side est déjà visible dans le code :

- normalisation des entrées hostiles dans [logs/src/Log/Application/Normalizer/LogPayloadNormalizer.php](logs/src/Log/Application/Normalizer/LogPayloadNormalizer.php)
- création de `LogEntry` dans [logs/src/Log/Application/Factory/LogEntryFactory.php](logs/src/Log/Application/Factory/LogEntryFactory.php)
- écriture disque de la queue dans [logs/src/Queue/Infrastructure/FileQueueWriter.php](logs/src/Queue/Infrastructure/FileQueueWriter.php)
- persistence SQL dans [logs/src/Persistence/Infrastructure/Doctrine/DoctrineLogWriter.php](logs/src/Persistence/Infrastructure/Doctrine/DoctrineLogWriter.php)

## Architecture des dossiers

### Liste simple

#### Actuel

- `src/Log/`
- `src/Queue/`
- `src/Persistence/`
- `src/DataFixtures/`
- `src/Shared/`

#### À venir

- `src/Ingestion/`
- `src/Search/`
- `src/Dashboard/`
- `src/Project/`
- `src/ApiToken/`

### Détail dossier par dossier

#### `src/Log/` - actuel

Module cœur du domaine log. Il porte la normalisation, les value objects, le fingerprint, les enums et la création de `LogEntry`.

#### `src/Queue/` - actuel

Module de queue disque. Il gère l'écriture atomique, la lecture batch, la configuration de queue et l'isolement des fichiers corrompus.

#### `src/Persistence/` - actuel

Module de persistence SQL. Il contient le contrat de persistence, le batch handler, le mapping vers les records et l'écriture Doctrine DBAL.

#### `src/Shared/` - actuel

Zone transversale réservée aux briques communes. À ce stade, le dossier sert surtout de squelette; il ne contient pas encore de logique métier stabilisée.

#### `src/DataFixtures/` - actuel

Fixtures de développement et de test pour peupler rapidement un environnement local.

#### `src/Ingestion/` - à venir

Module d'entrée de la plateforme. Il accueillera la validation minimale, l'orchestration du payload HTTP et l'appel vers la queue.

#### `src/Search/` - à venir

Module de recherche bornée. Il devra rester simple, indexé et strictement paginé.

#### `src/Dashboard/` - à venir

Module de lecture côté interface. Il servira à afficher les logs, les filtres et la navigation sans alourdir la partie écriture.

#### `src/Project/` - à venir

Module de gestion des projets logiques. Il portera les règles de rétention, l'identité du projet et les paramètres liés au périmètre de logs.

#### `src/ApiToken/` - à venir

Module d'authentification par jetons opaques. Il gérera le hash, la révocation, l'expiration et le suivi des usages.

## Quick start

1. Aller dans le dossier applicatif.

```bash
cd logs
```

1. Installer les dépendances PHP.

```bash
composer install
```

1. Préparer les variables d'environnement à partir des fichiers d'exemple.

```bash
cp ../env.symfony.example .env.local
cp ../.env.docker-compose.example ../.env
```

1. Adapter les valeurs locales si nécessaire, en particulier les ports Docker et les accès base de données.

1. Démarrer la stack locale depuis la racine du dépôt.

```bash
docker compose up -d
```

Services fournis par Docker :

- MariaDB
- Adminer
- phpMyAdmin
- smtp4dev

## Tests

La suite de tests Symfony/PHPUnit est configurée dans [logs/phpunit.dist.xml](logs/phpunit.dist.xml).

L'environnement de test utilise SQLite via `var/test.db`.

Commande de lancement :

```bash
cd logs
php bin/phpunit
```

## Queue et persistence

Le détail de la queue disque est documenté dans [README_Queue.md](README_Queue.md).

Le détail de la persistence SQL est documenté dans [README_persistence.md](README_persistence.md).

## Modèle de données

La base est pensée comme un append-only store, optimisé pour :

- écrire vite
- lire simplement
- purger facilement

Tables principales visées :

- projects
- api_tokens
- logs

## Recherche

La recherche doit rester bornée : pagination, LIMIT, index ciblés et chargements limités.

Éviter les lectures massives et les SELECT *.

## Tests et qualité

Règle de base : si ce n'est pas testé, ça n'existe pas.

Les cas critiques à couvrir en priorité :

- JSON invalide
- payload corrompu
- récursion
- données sensibles
- disque plein
- DB indisponible

## Roadmap courte

La direction actuelle du projet reste :

1. fiabiliser l'ingestion
2. bétonner la queue
3. stabiliser la persistence
4. ajouter la lecture bornée
5. construire le dashboard

## Statut

Projet en cours de développement, avec un socle write side déjà amorcé et une lecture encore en consolidation.

## Vision à venir

Le README doit aussi porter la direction produit et l'architecture cible. Cette partie décrit ce qui reste à construire et sert de repère pour la suite du projet.

### Philosophie d'ensemble

Le système vise à rester :

- robuste
- simple
- prévisible
- maintenable
- compatible mutualisé

Principes permanents :

- un log imparfait vaut mieux qu'un log perdu
- toutes les données externes sont hostiles
- aucun comportement implicite
- architecture explicite
- responsabilités uniques

### Architecture cible complète

Le système restera séparé en deux zones.

#### Write side à venir

Responsable de :

- ingestion
- validation minimale
- normalisation
- queue
- persistence

Priorité :

- écriture rapide
- aucune perte
- robustesse maximale

#### Read side à venir

Responsable de :

- dashboard
- recherche
- pagination
- filtres

Priorité :

- lecture simple
- requêtes bornées
- index ciblés

### Architecture HTTP

#### API à venir

Routes visées :

```txt
/api/*
```

Règles :

- JSON uniquement
- jamais de HTML

#### Dashboard à venir

Routes visées :

```txt
/dashboard/*
```

Règles :

- HTML uniquement
- jamais de JSON

### Contrat LogEntry

Un `LogEntry` doit rester :

- immutable
- toujours valide
- toujours normalisé

Champs obligatoires :

- message
- level
- domain
- env
- httpStatus
- client

Champs auto-corrigés :

- externalId
- createdAt
- clientDate
- uri
- ip

### Normalization

Toutes les données externes doivent être :

- normalisées
- limitées
- filtrées
- sécurisées

Limites visées :

- profondeur max : 5
- 50 éléments max
- string max : 1000 caractères

Données sensibles filtrées :

- password
- token
- authorization
- cookie

### Fingerprint

Base visée :

```txt
level|httpStatus|domain|uri|env
```

Règles :

- lowercase
- trim
- suppression query string
- sha1 tronqué à 16 caractères

Le fingerprint doit toujours être recalculé côté serveur.

### Queue

La queue absorbe les pics de charge.

Principe :

```txt
1 log = 1 fichier
```

Objectifs :

- robustesse
- écriture atomique
- isolation des erreurs
- aucune dépendance forte

Interdits :

- workers permanents
- RabbitMQ
- Redis obligatoire
- architecture async complexe

### Cron

Le cron doit traiter la queue selon ce flux :

```txt
queue
→ normalisation
→ persistence
→ suppression
```

Règles :

- traitement batch
- mémoire bornée
- traitement idempotent

### Base de données

La base doit rester pensée comme un append-only store.

Optimisée pour :

- écrire vite
- lire simplement
- purger facilement

Tables principales visées :

- projects
- api_tokens
- logs

### Recherche à venir

La recherche doit toujours rester bornée : pagination, LIMIT, requêtes ciblées et index adaptés.

Jamais :

- SELECT *
- chargement massif mémoire

### Tests à venir

Règle absolue : si ce n'est pas testé, ça n'existe pas.

Priorités :

1. Domain
2. Factory
3. Normalizer
4. Queue
5. Persistence
6. Search

Cas critiques :

- JSON invalide
- récursion
- payload corrompu
- disque plein
- DB indisponible
- données sensibles

### Technologies volontairement évitées

- API Platform
- Messenger
- Event Bus
- Event Sourcing
- CQRS complexe
- Microservices
- React non justifié
- Vue non justifié

### Objectif final

Construire un système :

- robuste
- lisible
- simple
- stable long terme
- maintenable
- compatible mutualisé

sans sur-ingénierie.
