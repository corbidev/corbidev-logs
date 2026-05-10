# Prochaine étape : Infrastructure

À ce stade du projet :

- le Domain est prêt
- les ValueObjects sont prêts
- les crash tests sont prêts
- la Factory est prête
- le FingerprintGenerator est prêt

La prochaine étape critique est maintenant :

```txt
src/Log/Infrastructure/
```

---

# Pipeline cible

```txt
API
→ Normalizer
→ Factory
→ LogEntry
→ Queue disque
→ Cron consumer
→ DB
```

---

# Architecture cible Infrastructure

```txt
src/
└── Log/
    └── Infrastructure/
        └── Queue/
            ├── FileQueueWriter.php
            ├── QueueFilenameGenerator.php
            ├── QueueDirectoryManager.php
            │
            └── Exception/
                ├── QueueWriteException.php
                ├── QueueDirectoryException.php
                └── QueueFilenameException.php
```

---

# Ordre de développement

## 1. FileQueueWriter

Composant le plus critique du système.

Responsabilités :

- 1 log = 1 fichier
- écriture atomique
- aucune perte silencieuse
- ultra robuste
- aucun lock complexe
- aucun daemon
- aucune dépendance Doctrine
- aucun service Symfony magique

Objectif :

```txt
écriture rapide
robustesse maximale
pipeline prédictible
```

---

## 2. QueueDirectoryManager

Responsabilités :

- création dossiers
- validation permissions
- robustesse disque
- auto création sécurisée

Doit gérer :

- dossier inexistant
- permissions invalides
- création concurrente
- dossiers cassés

---

## 3. QueueFilenameGenerator

Responsabilités :

- générer des noms uniques
- garantir tri chronologique
- éviter collisions

Format recommandé :

```txt
20260510_013015_ab12cd34ef56.json
```

Objectifs :

- stable
- sortable
- sans dépendance DB
- filesystem friendly

---

# Tests critiques à écrire

## Tests unitaires

Pour :

- FileQueueWriter
- QueueDirectoryManager
- QueueFilenameGenerator

---

# Crash tests obligatoires

## FileQueueWriter

Tester :

- dossier inexistant
- dossier non writable
- permissions cassées
- disque plein simulé
- payload énorme
- JSON invalide
- caractères binaires
- UTF8 invalide
- écriture concurrente
- milliers de fichiers

---

## QueueDirectoryManager

Tester :

- mkdir failure
- permissions refusées
- path invalide
- race conditions
- dossier supprimé pendant écriture

---

## QueueFilenameGenerator

Tester :

- collisions
- génération massive
- stabilité format
- caractères invalides
- tri chronologique

---

# Puis seulement après

## Controller API ingestion

```txt
POST /api/ingest
```

Responsabilités :

- recevoir payload JSON
- validation minimale
- normalizer
- factory
- queue writer
- réponse HTTP rapide

Aucune logique métier lourde.

---

# Puis

## Tests d’intégration ingestion

Tester :

- payload valide
- payload hostile
- JSON invalide
- queue indisponible
- disque plein
- timeout
- gros payload
- données sensibles

---

# Puis

## Cron consumer

Responsabilités :

- lire queue disque
- traitement batch
- persistence DB
- idempotence
- isolation fichiers corrompus

Jamais :

- daemon permanent
- boucle infinie
- worker mémoire long terme

---

# Puis

## Persistence DB

Responsabilités :

- append-only
- SQL simple
- index ciblés
- pagination obligatoire

---

# Puis

## Dashboard

Seulement une fois :

- ingestion stable
- queue stable
- persistence stable
- consumer stable

Priorité absolue :

```txt
WRITE SIDE FIRST
READ SIDE AFTER
```