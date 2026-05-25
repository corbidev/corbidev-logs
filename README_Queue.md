# Queue

Objectif

Le composant "Queue" est responsable du stockage temporaire des logs avant persistence en base de données.

La queue est conçue pour :

- absorber les pics de charge
- garantir une écriture rapide
- éviter toute perte de logs
- isoler les erreurs
- fonctionner sur hébergement mutualisé
- ne dépendre d’aucun daemon complexe

La queue est la colonne vertébrale du WRITE SIDE.

---

Philosophie

La queue doit être :

- simple
- robuste
- prédictible
- append-only
- tolérante aux corruptions

Priorité absolue :

ne jamais perdre un log valide

---

Architecture

src/
└── Queue/
    ├── Application/
    │   ├── ✅ QueueWriterInterface.php
    │   └── ✅ QueueReaderInterface.php
    │
    ├── Infrastructure/
    │   ├── FileQueueWriter.php
    │   ├── FileQueueReader.php
    │   ├── QueueFileNamingStrategy.php
    │   ├── CorruptedQueueFileManager.php
    │   └── ✅ QueueConfiguration.php
    │
    └── Tests/

---

Responsabilités

FileQueueWriter

Responsable :

- écriture atomique disque
- création des fichiers queue
- sérialisation JSON
- gestion minimale des erreurs disque

Ne doit jamais :

- faire de logique métier
- recalculer les données métier
- modifier les logs

---

FileQueueReader

Responsable :

- lecture batch de fichiers queue
- récupération ordonnée
- limitation mémoire
- isolation des fichiers corrompus

Ne doit jamais :

- charger toute la queue en mémoire
- faire de logique métier

---

QueueFileNamingStrategy

Responsable :

- générer des noms de fichiers uniques
- garantir l’ordre approximatif
- éviter les collisions

---

CorruptedQueueFileManager

Responsable :

- isoler les fichiers invalides
- déplacer les fichiers corrompus
- empêcher le blocage du consumer

---

Règles fondamentales

1 log = 1 fichier

Toujours.

Exemple :

var/queue/logs/
├── 20260510_021522_ab12cd34.json
├── 20260510_021523_ef56gh78.json
└── 20260510_021524_ij90kl12.json

Avantages :

- simplicité
- isolation corruption
- suppression facile
- retry indépendant
- aucune contention complexe

---

Format des fichiers

Chaque fichier contient un unique JSON valide.

Exemple :

{
  "message": "Paiement refusé",
  "level": "error",
  "domain": "billing",
  "env": "prod",
  "httpStatus": 500,
  "fingerprint": "a1b2c3d4e5f6a7b8",
  "createdAt": "2026-05-10T02:15:22+00:00"
}

---

Écriture disque

Règles

Toujours :

- écriture atomique
- "LOCK_EX"
- fichier temporaire puis rename
- UTF-8 valide
- JSON_THROW_ON_ERROR

Jamais :

- append dans un gros fichier
- lock complexe
- mémoire partagée
- queue SQL
- daemon permanent

---

Atomicité

L’écriture doit suivre ce flux :

1. créer fichier temporaire
2. écrire contenu JSON
3. flush
4. rename atomique

Jamais :

file_put_contents direct sans protection

---

Gestion des erreurs

Si écriture impossible

Le système doit :

- lever une exception technique explicite
- ne jamais produire de fichier partiel

---

Si JSON invalide

Le log doit être rejeté avant écriture.

---

Si fichier corrompu détecté

Le fichier doit être déplacé vers :

var/queue/corrupted/

Jamais supprimé immédiatement.

---

Lecture queue

Lecture batch

Toujours :

- lecture bornée
- LIMIT explicite
- traitement progressif

Exemple :

100 fichiers maximum par batch

---

Ordre de lecture

Ordre recommandé :

FIFO approximatif

Basé sur :

timestamp + random suffix

Pas besoin de garantie stricte.

---

Suppression

Après persistence réussie :

delete fichier queue

Jamais avant.

---

Retry

Les retries doivent être limités.

Exemple :

3 tentatives maximum

Après dépassement :

→ déplacer dans failed/

---

Répertoires recommandés

var/
└── queue/
    ├── logs/
    ├── processing/
    ├── corrupted/
    └── failed/

---

Contraintes techniques

Hébergement mutualisé

La queue doit fonctionner :

- sans Redis
- sans RabbitMQ
- sans Supervisor
- sans daemon
- sans process permanent

Le système doit fonctionner uniquement avec :

PHP CLI + CRON

---

Performance

Priorité :

vitesse d’écriture

Toujours privilégier :

- petits fichiers
- SQL absent du write side
- allocations minimales
- traitements courts

---

Sécurité

Les données queue sont considérées hostiles.

Toujours :

- relire le JSON avant persistence
- vérifier la structure
- limiter la taille des payloads

Jamais :

- faire confiance au disque
- faire confiance aux données queue

---

Tests obligatoires

Writer

Tester :

- écriture valide
- disque inaccessible
- permissions invalides
- collision nom fichier
- JSON invalide
- payload énorme

---

Reader

Tester :

- lecture batch
- lecture vide
- fichier corrompu
- suppression après traitement
- ordre lecture
- mémoire bornée

---

Corruption

Tester :

- JSON tronqué
- UTF-8 invalide
- fichier vide
- fichier partiellement écrit

---

Interdictions

Interdit :

- RabbitMQ
- Redis obligatoire
- workers permanents
- Event Bus
- queues SQL complexes
- append massif
- locks distribués
- multi-threading
- polling agressif

---

Objectif final

La queue doit permettre :

HTTP Request
→ Validation minimale
→ Queue disque
→ Réponse HTTP rapide

Puis plus tard :

CRON
→ Lecture queue
→ Persistence DB
→ Suppression fichier

---

Résultat attendu

Même si :

- la DB tombe
- le réseau tombe
- le consumer plante
- le dashboard crash
- Doctrine échoue

Le log doit déjà être sécurisé sur disque.

C’est la responsabilité principale de la queue.

---

Runbook d'exploitation

Objectif

Exécuter et surveiller le traitement de queue sans connaissance implicite.

Pré-requis

- dépendances installées dans `logs/`
- variables d'environnement configurées
- accès base de données disponible pour la persistence

Commande de traitement

Depuis la racine du dépôt :

```bash
cd logs
php bin/console app:queue:process
```

Traitement borné explicite (exemple 200 items max) :

```bash
cd logs
php bin/console app:queue:process 200
```

Lecture du résultat

La commande affiche un tableau avec :

- Processed
- Failed
- MovedToFailed
- Retries
- Total
- Duration

Interprétation rapide

- `Failed = 0` et `MovedToFailed = 0` : batch nominal
- `Retries > 0` : présence de fichiers instables, surveiller le lot suivant
- `MovedToFailed > 0` : fichiers à analyser dans `var/queue/failed/`

Surveillance opérationnelle minimale

Vérifier les répertoires :

- `var/queue/logs/` (backlog à traiter)
- `var/queue/corrupted/` (fichiers invalides)
- `var/queue/failed/` (échecs après retries)

Relance recommandée

- lancer la commande de façon périodique via CRON
- conserver une limite de batch stable (ex: 100 ou 200)
- éviter les batchs massifs non maîtrisés

Gestion d'incident

Si la DB est indisponible :

- ne pas supprimer manuellement `var/queue/logs/`
- rétablir la DB
- relancer `app:queue:process`

Si `failed/` augmente :

- isoler un échantillon de fichiers
- vérifier format JSON et données minimales
- corriger la cause (données ou persistence)
- retraiter manuellement si nécessaire
