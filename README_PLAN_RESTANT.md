# Plan détaillé des travaux restants

Ce document décrit de manière opérationnelle ce qu'il reste à faire sur la plateforme de logs.

## 1. Objectif global

Construire un pipeline complet et stable:

1. ingestion HTTP robuste
2. mise en queue disque fiable
3. traitement batch idempotent
4. persistence SQL solide
5. lecture (search + dashboard) bornée

## 2. État actuel résumé

Déjà en place:

- module Log (normalisation, LogEntry, fingerprint, value objects)
- module Queue (writer/reader/configuration/corrupted files)
- module Persistence (batch handler + writer Doctrine DBAL)
- base de tests existante

Reste à livrer:

- module Ingestion finalisé
- cron de consommation de queue en production
- module Search borné
- module Dashboard exploitable
- module Project et ApiToken
- clarification finale de l'authentification

## 3. Priorités (ordre strict)

P0:

- terminer le write side de bout en bout
- fiabiliser observabilité et reprise sur erreur
- valider les scénarios critiques en test

P1:

- construire la lecture bornée (search)
- exposer un dashboard minimal utile

P2:

- enrichissements UX
- optimisations secondaires

## 4. Plan par phases

### Phase A - Finaliser Ingestion (P0)

Objectif:

- accepter POST /api/logs de manière robuste et prédictible

À faire:

- [ ] créer/compléter le module src/Ingestion avec Application, Domain, Infrastructure
- [ ] implémenter l'endpoint API (JSON only, jamais HTML)
- [ ] valider payload minimal et format erreurs/success
- [ ] brancher normalizer -> factory -> queue writer
- [ ] renvoyer 202 Accepted sur ingestion réussie
- [ ] standardiser codes d'erreur (invalid_json, invalid_payload, unauthorized, etc.)
- [ ] ajouter tests fonctionnels d'ingestion (cas nominal + hostiles)

Critères d'acceptation:

- endpoint stable sous charge modérée
- aucun crash sur payload hostile
- écriture queue déclenchée pour chaque log accepté

### Phase B - Consumer/Cron de queue (P0)

Objectif:

- traiter la queue en batch, sans perte et de façon idempotente

À faire:

- [ ] finaliser la commande de traitement queue (app:queue:process)
- [ ] brancher reader queue -> persistence handler -> delete/failed/corrupted
- [ ] implémenter stratégie retry bornée
- [ ] tracer métriques de batch (persisted, failed, duration)
- [ ] gérer explicitement DB indisponible et disque plein
- [ ] ajouter tests d'intégration de pipeline batch

Critères d'acceptation:

- pas de suppression avant persistence réussie
- relance de la commande sans effets de bord critiques
- fichiers corrompus isolés correctement

### Phase C - Durcir Persistence SQL (P0)

Objectif:

- garantir une persistence fiable et bornée

À faire:

- [ ] finaliser schéma SQL cible (projects, api_tokens, logs)
- [ ] vérifier index utiles (filtres, pagination, tri temporel)
- [ ] consolider mapping LogEntry -> record SQL
- [ ] borner taille batch et erreurs retournées
- [ ] implémenter purge/rétention selon règles projet
- [ ] ajouter tests DB indisponible, timeouts, rollback

Critères d'acceptation:

- insertion batch cohérente
- erreurs persistées/loguées sans casser le pipeline
- perfs d'écriture conformes à la cible mutualisée

### Phase D - Authentification ApiToken (P0/P1)

Objectif:

- stabiliser l'auth ingestion par jetons opaques

À faire:

- [ ] implémenter module src/ApiToken (création, hash, révocation, expiration)
- [ ] lier tokens à un Project
- [ ] ajouter contrôle Authorization Bearer sur endpoint ingestion
- [ ] gérer last_used_at et audit minimal
- [ ] finaliser la documentation de la stratégie tokens opaques (sans JWT)

Critères d'acceptation:

- requête sans token refusée proprement
- token expiré/révoqué refusé
- token valide autorise l'ingestion

### Phase E - Module Project (P1)

Objectif:

- gérer les projets et la rétention associée

À faire:

- [ ] implémenter src/Project (modèle + services)
- [ ] CRUD minimal projet
- [ ] slug unique et règles de nommage
- [ ] paramétrer retention_days par projet

Critères d'acceptation:

- chaque log rattaché à un projet
- rétention configurable par projet

### Phase F - Search borné (P1)

Objectif:

- fournir recherche fiable sans dérive de charge

À faire:

- [ ] implémenter src/Search (requêtes bornées)
- [ ] pagination obligatoire et LIMIT strict
- [ ] filtres minimum: période, level, domain, project, fingerprint
- [ ] tri cohérent et stable
- [ ] tests de pagination et filtres combinés

Critères d'acceptation:

- aucune requête non bornée
- temps de réponse stable sur jeu de données réaliste

### Phase G - Dashboard minimal (P1)

Objectif:

- rendre la lecture utilisable rapidement

À faire:

- [ ] implémenter src/Dashboard
- [ ] page liste logs paginée
- [ ] page détail log
- [ ] filtres principaux reliés à Search
- [ ] erreurs UI gérées proprement

Critères d'acceptation:

- navigation simple et fluide
- aucun endpoint dashboard ne retourne du JSON brut hors besoin explicite

### Phase H - Qualité, exploitation, documentation (P0 continu)

Objectif:

- garantir la maintenabilité et l'exploitabilité

À faire:

- [ ] compléter couverture tests sur cas critiques
- [ ] journaliser événements techniques clés
- [ ] documenter runbook cron/reprise incident
- [ ] harmoniser tous les README avec l'état réel
- [ ] valider procédure d'installation locale et CI

Critères d'acceptation:

- exécution tests fiable
- docs cohérentes avec le code
- exploitation possible sans connaissance implicite

## 5. Backlog technique transverse

- [ ] stratégie uniforme d'erreurs métier et techniques
- [ ] conventions de naming modules/classes
- [ ] conventions de payload de réponse API
- [ ] gestion centralisée des limites (taille, profondeur, batch)
- [ ] nettoyage des dépendances non utilisées

## 6. Checkpoints de validation recommandés

Après chaque phase:

1. exécuter les tests ciblés du module
2. exécuter php bin/phpunit
3. valider manuellement le flux principal concerné
4. mettre à jour README.md et ce plan si besoin

Commandes utiles:

```bash
cd logs
php bin/phpunit
php bin/console cache:clear
php bin/console app:queue:process
```

## 7. Risques à surveiller

- dérive de complexité (trop de couches prématurées)
- régressions silencieuses sur queue/persistence
- dérive des règles d'auth (tokens opaques) entre code et documentation
- requêtes search non bornées
- documentation qui diverge du code

## 8. Définition de terminé (DoD)

Le projet est considéré "MVP complet" quand:

- ingestion auth + queue + cron + persistence est stable en bout en bout
- tests critiques passent de façon répétable
- recherche paginée est en place
- dashboard minimal est utilisable
- documentation install/exploit est à jour
