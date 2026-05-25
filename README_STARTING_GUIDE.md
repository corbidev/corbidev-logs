# Par quoi commencer ?

## Note de contexte

Ce guide décrit l'ordre de construction initial.
Au 25/05/2026, plusieurs étapes sont déjà livrées (Ingestion, ApiToken, Project, Search borné, Dashboard liste+détail).
Les recommandations ci-dessous restent utiles pour prioriser ce qui n'est pas encore finalisé.

Vu ton architecture et tes contraintes :

- Symfony 8
- PHP 8.4
- Hébergement mutualisé
- Priorité absolue à la robustesse d'écriture
- Architecture simple et prévisible

Il faut commencer par le WRITE SIDE minimal.

Pas le dashboard.
Pas la recherche.
Pas Doctrine complexe.

L'objectif initial :

> réussir à accepter et stocker des logs sans jamais perdre de données.

---

# 1. Créer le squelette projet

Structure immédiatement stable :

```txt
src/

- Ingestion/
- Queue/
- ✅ Log/
- ✅ Persistence/
- Search/
- Dashboard/
- Project/
- ApiToken/
- Shared/
```

Et dans chaque module :

```txt
Application/
Domain/
Infrastructure/
```

Même vide.

Cela évite de tout déplacer plus tard.

---

# 2. Écrire les tests du normalizer

Le normalizer est le cœur du système.

Tu reçois des payloads hostiles :

- JSON cassé
- récursion
- tableaux énormes
- données sensibles
- strings géantes
- objets inconnus

Commence donc par :

```txt
tests/Log/Domain/
```

Puis écrire les tests pour :

- profondeur max
- 50 éléments max
- string max 1000
- suppression password/token
- null safe
- unicode
- JSON invalide

Pourquoi ?

> Si le normalizer est solide, tout le reste devient simple.

---

# 3. Créer le LogEntry immutable

Le vrai cœur métier.

Exemple :

```txt
src/Log/Domain/Model/LogEntry.php
```

Il doit être :

- immutable
- toujours valide
- déjà normalisé

Il ne dépend PAS de Symfony.

Commence ultra simple :

```php
final readonly class LogEntry
{
    public function __construct(
        public string $message,
        public string $level,
        public string $domain,
        public string $env,
        public int $httpStatus,
        public string $client,
    ) {}
}
```

Puis enrichir progressivement.

---

# 4. Créer le Fingerprint Service

Très important tôt dans le projet.

Car :

- groupement
- déduplication
- recherche
- statistiques

vont dépendre de lui.

Service pur :

- deterministic
- testé
- sans Symfony

Règle :

```txt
level|httpStatus|domain|uri|env
```

Puis :

- trim
- lowercase
- suppression query string
- sha1 tronqué

---

# 5. Construire la queue disque AVANT la DB

Très important.

Ne commence PAS par Doctrine.

Ton système réel repose sur :

- absorber les pics
- ne rien perdre
- survivre aux erreurs DB

Donc il faut vite construire :

```txt
QueueWriter
QueueReader
QueueFileNamer
```

Et tester :

- écriture atomique
- corruption
- disque plein
- retry
- lecture batch

C'est le vrai moteur du système.

---

# 6. Construire l'endpoint ingestion minimal

Ensuite seulement :

```txt
POST /api/logs
```

Flux :

```txt
HTTP JSON
→ validation minimale
→ normalizer
→ queue writer
→ 202 Accepted
```

Surtout :

- pas Doctrine
- pas d'hydratation lourde
- pas de logique métier

Le contrôleur doit être ultra fin.

---

# 7. Ajouter le cron processor

Le cron :

- lit la queue
- transforme en LogEntry
- persiste

Exemple :

```bash
php bin/console app:queue:process
```

Traitement batch :

- mémoire bornée
- idempotent
- robuste

---

# 8. Ensuite seulement : persistence SQL

Quand l'ingestion est déjà fiable.

Là :

- tables
- index
- pagination
- recherche

Mais seulement après avoir sécurisé :

- ingestion
- queue
- normalisation

---

# Ce qu'il NE faut PAS faire maintenant

Évite totalement :

- dashboard moderne
- HTMX
- Alpine
- graphiques
- auth complexe
- multi projets avancé
- recherche full text
- websocket
- stats temps réel

Tant que :

- la queue n'est pas béton
- le normalizer n'est pas testé

---

# Roadmap idéale

## Phase 1 — Fondations

- structure modules
- tests normalizer
- LogEntry
- fingerprint
- queue disque

## Phase 2 — Ingestion

- endpoint API
- validation minimale
- écriture queue
- tests corruption

## Phase 3 — Traitement

- cron
- persistence SQL
- purge
- retry

## Phase 4 — Lecture

- recherche
- pagination
- filtres

## Phase 5 — Dashboard

- Twig
- HTMX
- Alpine
- UX

---

# Priorité absolue

Le plus rentable au début :

> réussir à écrire 100 000 logs sans en perdre un seul.

L'UX avancée du dashboard peut attendre.
