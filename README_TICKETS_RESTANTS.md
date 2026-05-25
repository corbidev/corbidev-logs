# Tickets restants

Ce fichier découpe le plan global en tickets unitaires, prêts à être traités un par un.

## Convention

- un ticket = une livraison petite et testable
- chaque ticket doit avoir un résultat visible
- chaque ticket doit être validé avant le suivant quand il touche au flux principal

## P0 - Write side de bout en bout

### ING-001 - Créer le squelette du module Ingestion

Objectif :

- poser `src/Ingestion/Application`, `src/Ingestion/Domain` et `src/Ingestion/Infrastructure`

Critères de fin :

- l'arborescence existe
- aucun code métier n'est mélangé avec d'autres modules

### ING-002 - Créer l'endpoint `POST /api/logs`

Objectif :

- exposer une entrée HTTP JSON-only pour l'ingestion

Critères de fin :

- la route répond
- les erreurs HTTP sont explicites
- aucune réponse HTML

### ING-003 - Valider le payload minimal d'ingestion

Objectif :

- vérifier le format du body, la présence de `logs` et les cas invalides

Critères de fin :

- JSON invalide refusé proprement
- payload vide ou mal formé refusé proprement
- messages d'erreur stables

### ING-004 - Brancher normalizer, factory et queue

Objectif :

- relier le flux ingestion vers normalisation puis écriture queue

Critères de fin :

- un log valide produit un fichier queue
- aucun crash sur payload hostile

### ING-005 - Retourner `202 Accepted` à l'ingestion

Objectif :

- standardiser la réponse de succès de l'API

Critères de fin :

- succès = `202 Accepted`
- réponse JSON stable

### ING-006 - Ajouter les tests fonctionnels d'ingestion

Objectif :

- couvrir le flux nominal et les cas hostiles

Critères de fin :

- tests du cas valide
- tests JSON invalide
- tests payload hostile

### Q-001 - Finaliser la commande de traitement de queue

Objectif :

- rendre la commande `app:queue:process` exécutable et fiable

Critères de fin :

- commande disponible
- batch traité sans erreur fatale

### Q-002 - Brancher reader, persistence et suppression

Objectif :

- relier lecture queue -> persistence -> suppression du fichier

Critères de fin :

- suppression seulement après persistence réussie
- fichiers corrompus isolés

### Q-003 - Implémenter la stratégie de retry bornée

Objectif :

- limiter les tentatives de relecture/persistence des fichiers problématiques

Critères de fin :

- nombre de retries borné
- comportement déterministe

### Q-004 - Ajouter les métriques de batch

Objectif :

- tracer le volume persisté, le volume en échec et la durée de traitement

Critères de fin :

- métriques disponibles dans les logs ou le résultat de commande

### P-001 - Finaliser le schéma SQL cible

Objectif :

- stabiliser les tables `projects`, `api_tokens`, `logs`

Critères de fin :

- schéma cohérent avec le modèle métier
- colonnes et types validés

### P-002 - Consolider le mapping `LogEntry` vers SQL

Objectif :

- garantir que les champs du domaine sont persistés correctement

Critères de fin :

- mapping lisible
- champs obligatoires couverts

### P-003 - Borner les batchs de persistence

Objectif :

- empêcher les écritures massives non contrôlées

Critères de fin :

- taille de batch limitée
- comportement stable sur lot volumineux

### P-004 - Tester la DB indisponible et les rollback

Objectif :

- couvrir les erreurs SQL et la récupération

Critères de fin :

- rollback vérifié
- erreur technique explicite

## P0/P1 - Authentification

### AUTH-001 - Créer le module ApiToken

Objectif :

- poser la structure `src/ApiToken`

Critères de fin :

- arborescence prête
- séparation claire domaine / application / infrastructure

### AUTH-002 - Implémenter la création de token opaque

Objectif :

- générer un token utilisable côté ingestion

Critères de fin :

- token généré
- hash stocké

### AUTH-003 - Ajouter révocation et expiration

Objectif :

- permettre de désactiver un token sans supprimer l'historique utile

Critères de fin :

- token révoqué refusé
- token expiré refusé

### AUTH-004 - Brancher l'autorisation sur ingestion

Objectif :

- protéger `POST /api/logs` par `Authorization: Bearer ...`

Critères de fin :

- accès sans token refusé
- accès avec token valide accepté

### AUTH-005 - Documenter la stratégie tokens opaques (sans JWT)

Objectif :

- documenter explicitement la stratégie d'authentification retenue

Critères de fin :

- position documentée
- aucune ambiguïté JWT/tokens opaques dans la documentation

## P1 - Modules métier de lecture

### PRJ-001 - Créer le module Project

Objectif :

- poser la base métier des projets logiques

Critères de fin :

- arborescence prête
- modèle de projet initial défini

### PRJ-002 - Gérer la rétention par projet

Objectif :

- rendre `retention_days` exploitable

Critères de fin :

- valeur persistée
- règle utilisable par la purge

### SRCH-001 - Créer le module Search borné

Objectif :

- poser les requêtes bornées pour la lecture

Critères de fin :

- pagination obligatoire
- LIMIT explicite

### SRCH-002 - Ajouter les filtres principaux

Objectif :

- permettre de filtrer par période, niveau, domaine, projet et fingerprint

Critères de fin :

- filtres combinables
- comportement stable

### DASH-001 - Créer le module Dashboard

Objectif :

- préparer l'interface de lecture

Critères de fin :

- arborescence prête
- séparation claire avec le write side

### DASH-002 - Créer la liste paginée des logs

Objectif :

- afficher les logs de manière bornée

Critères de fin :

- pagination fonctionnelle
- pas de chargement massif

### DASH-003 - Créer la vue détail d'un log

Objectif :

- afficher un log complet et lisible

Critères de fin :

- vue stable
- données principales visibles

## P0 continu - Qualité et documentation

### DOC-001 - Harmoniser tous les README

Objectif :

- faire correspondre la documentation au code réel

Critères de fin :

- aucun README contradictoire

### DOC-002 - Documenter l'exploitation de la queue

Objectif :

- expliquer comment lancer et surveiller le traitement de queue

Critères de fin :

- runbook simple et exécutable

### TEST-001 - Compléter les tests des cas critiques

Objectif :

- couvrir JSON invalide, récursion, disque plein, DB indisponible, payload corrompu

Critères de fin :

- cas critiques couverts
- non-régression détectable

### OPS-001 - Valider l'installation locale et CI

Objectif :

- vérifier que l'installation et les tests passent de façon répétable

Critères de fin :

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
