# Issues restantes

Ce document reformule le backlog en issues unitaires, prêtes à être reprises une par une.

## Format

- `title` : titre de l'issue
- `labels` : catégorisation minimale
- `depends_on` : dépendances si nécessaire
- `body` : objectif, contenu attendu et critères de fin

## P0 - Write side

### ING-001

title: `Create Ingestion module skeleton`

labels:

- `ingestion`
- `p0`
- `architecture`

depends_on: none

body:

- créer `src/Ingestion/Application`, `src/Ingestion/Domain`, `src/Ingestion/Infrastructure`
- ne pas mélanger de logique d'un autre module
- valider que l'arborescence existe et reste vide de code parasite

acceptance:

- l'arborescence est présente
- le module est prêt à recevoir l'endpoint et la validation

### ING-002

title: `Expose POST /api/logs`

labels:

- `api`
- `ingestion`
- `p0`

depends_on:

- `ING-001`

body:

- exposer l'endpoint d'ingestion JSON-only
- renvoyer une réponse explicite et stable
- interdire toute réponse HTML

acceptance:

- la route répond
- les erreurs sont lisibles et cohérentes
- le succès ne produit pas de HTML

### ING-003

title: `Validate ingestion payload`

labels:

- `api`
- `validation`
- `p0`

depends_on:

- `ING-002`

body:

- vérifier le format JSON du body
- contrôler la présence et la structure de `logs`
- refuser proprement les payloads invalides

acceptance:

- JSON invalide refusé
- payload vide ou mal formé refusé
- messages d'erreur stables

### ING-004

title: `Connect normalizer factory and queue`

labels:

- `ingestion`
- `queue`
- `p0`

depends_on:

- `ING-003`

body:

- brancher le flux ingestion vers normalisation
- créer le `LogEntry`
- écrire le résultat dans la queue disque

acceptance:

- un log valide produit un fichier queue
- un payload hostile ne casse pas le flux

### ING-005

title: `Return 202 Accepted on ingestion`

labels:

- `api`
- `response`
- `p0`

depends_on:

- `ING-004`

body:

- standardiser la réponse de succès de l'API
- renvoyer `202 Accepted` lorsque le log est accepté

acceptance:

- succès = `202 Accepted`
- réponse JSON stable

### ING-006

title: `Add ingestion functional tests`

labels:

- `tests`
- `ingestion`
- `p0`

depends_on:

- `ING-005`

body:

- couvrir le cas nominal
- couvrir les cas hostiles
- valider les erreurs JSON et payload

acceptance:

- tests du cas valide
- tests JSON invalide
- tests payload hostile

### Q-001

title: `Finalize queue processing command`

labels:

- `queue`
- `cron`
- `p0`

depends_on: none

body:

- rendre `app:queue:process` exécutable et stable
- préparer le batch processing

acceptance:

- commande disponible
- batch traité sans erreur fatale

### Q-002

title: `Wire queue reader to persistence`

labels:

- `queue`
- `persistence`
- `p0`

depends_on:

- `Q-001`

body:

- relier reader queue -> persistence -> suppression
- ne supprimer qu'après succès de persistence

acceptance:

- suppression seulement après persistence réussie
- fichiers corrompus isolés

### Q-003

title: `Implement bounded retry strategy`

labels:

- `queue`
- `retry`
- `p0`

depends_on:

- `Q-002`

body:

- limiter les tentatives de relecture et de persistence
- garder un comportement déterministe

acceptance:

- nombre de retries borné
- comportement reproductible

### Q-004

title: `Add batch processing metrics`

labels:

- `queue`
- `observability`
- `p0`

depends_on:

- `Q-002`

body:

- tracer le volume persisté
- tracer le volume en échec
- tracer la durée de traitement

acceptance:

- métriques visibles dans les logs ou la commande

### P-001

title: `Finalize SQL schema`

labels:

- `persistence`
- `schema`
- `p0`

depends_on: none

body:

- stabiliser les tables `projects`, `api_tokens`, `logs`
- valider colonnes et types

acceptance:

- schéma cohérent avec le domaine
- colonnes validées

### P-002

title: `Consolidate LogEntry to SQL mapping`

labels:

- `persistence`
- `mapping`
- `p0`

depends_on:

- `P-001`

body:

- garantir que les champs du domaine sont persistés correctement
- garder un mapping lisible et borné

acceptance:

- mapping clair
- champs obligatoires couverts

### P-003

title: `Bound persistence batch size`

labels:

- `persistence`
- `performance`
- `p0`

depends_on:

- `P-002`

body:

- empêcher les écritures massives non contrôlées
- garder une taille de batch stable

acceptance:

- taille de batch limitée
- comportement stable sur lot volumineux

### P-004

title: `Test SQL failure and rollback handling`

labels:

- `tests`
- `persistence`
- `p0`

depends_on:

- `P-002`

body:

- couvrir les erreurs SQL
- vérifier la récupération et le rollback

acceptance:

- rollback vérifié
- erreur technique explicite

## P0/P1 - Auth

### AUTH-001

title: `Create ApiToken module skeleton`

labels:

- `auth`
- `p0`

depends_on: none

body:

- poser `src/ApiToken/Application`, `src/ApiToken/Domain`, `src/ApiToken/Infrastructure`

acceptance:

- arborescence prête
- séparation des responsabilités claire

### AUTH-002

title: `Implement opaque token generation`

labels:

- `auth`
- `security`
- `p0`

depends_on:

- `AUTH-001`

body:

- générer un token utilisable côté ingestion
- stocker le hash uniquement

acceptance:

- token généré
- hash stocké

### AUTH-003

title: `Add token revocation and expiration`

labels:

- `auth`
- `security`
- `p0`

depends_on:

- `AUTH-002`

body:

- désactiver un token sans supprimer l'historique utile

acceptance:

- token révoqué refusé
- token expiré refusé

### AUTH-004

title: `Protect ingestion with bearer token`

labels:

- `auth`
- `api`
- `p0`

depends_on:

- `AUTH-003`

body:

- protéger `POST /api/logs`
- refuser l'accès sans token valide

acceptance:

- accès sans token refusé
- accès avec token valide accepté

### AUTH-005

title: `Clarify JWT versus opaque tokens`

labels:

- `auth`
- `docs`
- `p0`

depends_on:

- `AUTH-004`

body:

- aligner documentation et dépendances sur la stratégie retenue

acceptance:

- position documentée
- aucune ambiguïté dans les README

## P1 - Read side

### PRJ-001

title: `Create Project module skeleton`

labels:

- `project`
- `p1`

depends_on: none

body:

- poser la base métier des projets logiques

acceptance:

- arborescence prête
- modèle initial défini

### PRJ-002

title: `Support retention per project`

labels:

- `project`
- `retention`
- `p1`

depends_on:

- `PRJ-001`

body:

- rendre `retention_days` exploitable

acceptance:

- valeur persistée
- règle utilisable par la purge

### SRCH-001

title: `Create bounded Search module`

labels:

- `search`
- `p1`

depends_on: none

body:

- poser les requêtes bornées pour la lecture

acceptance:

- pagination obligatoire
- LIMIT explicite

### SRCH-002

title: `Add main search filters`

labels:

- `search`
- `filters`
- `p1`

depends_on:

- `SRCH-001`

body:

- permettre de filtrer par période, niveau, domaine, projet et fingerprint

acceptance:

- filtres combinables
- comportement stable

### DASH-001

title: `Create Dashboard module skeleton`

labels:

- `dashboard`
- `p1`

depends_on: none

body:

- préparer l'interface de lecture

acceptance:

- arborescence prête
- séparation claire avec le write side

### DASH-002

title: `Build paginated log list`

labels:

- `dashboard`
- `ui`
- `p1`

depends_on:

- `DASH-001`
- `SRCH-001`

body:

- afficher les logs de manière bornée

acceptance:

- pagination fonctionnelle
- pas de chargement massif

### DASH-003

title: `Build log detail view`

labels:

- `dashboard`
- `ui`
- `p1`

depends_on:

- `DASH-002`

body:

- afficher un log complet et lisible

acceptance:

- vue stable
- données principales visibles

## P0 continu - Qualité et documentation

### DOC-001

title: `Harmonize all README files`

labels:

- `docs`
- `p0`

depends_on: none

body:

- faire correspondre la documentation au code réel

acceptance:

- aucun README contradictoire

### DOC-002

title: `Document queue operations`

labels:

- `docs`
- `queue`
- `p0`

depends_on:

- `Q-001`

body:

- expliquer comment lancer et surveiller le traitement de queue

acceptance:

- runbook simple et exécutable

### TEST-001

title: `Cover critical failure cases`

labels:

- `tests`
- `p0`

depends_on: none

body:

- couvrir JSON invalide, récursion, disque plein, DB indisponible, payload corrompu

acceptance:

- cas critiques couverts
- non-régression détectable

### OPS-001

title: `Validate local install and CI`

labels:

- `ops`
- `tests`
- `p0`

depends_on: none

body:

- vérifier que l'installation et les tests passent de façon répétable

acceptance:

- installation locale documentée
- suite de tests exécutable

## Ordre recommandé

1. ING-001 à ING-006
2. Q-001 à Q-004
3. P-001 à P-004
4. AUTH-001 à AUTH-005
5. PRJ-001 à PRJ-002
6. SRCH-001 à SRCH-002
7. DASH-001 à DASH-003
8. DOC-001 à DOC-002
9. TEST-001
10. OPS-001
