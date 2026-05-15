# README — Stratégie de tests Database

# Philosophie

Le projet est orienté :

```txt
robustesse
prédictibilité
simplicité
résilience IO
persistence append-only
```

Les tests doivent simuler :

- de vrais accès disque
- de vraies erreurs de persistence
- des batchs réels
- des payloads hostiles
- des corruptions
- des crashs infrastructure

Le but n’est PAS de tester Doctrine.

Le but est de tester :

```txt
notre architecture
nos invariants
nos handlers
nos writers
nos erreurs
nos comportements de crash
notre résilience
```

---

# Objectifs des tests

Les tests doivent garantir :

```txt
stabilité
résilience
prévisibilité
robustesse
```

même avec :

```txt
payloads hostiles
JSON invalides
DB indisponible
IO cassée
batchs énormes
fichiers corrompus
```

---

# Choix retenu

## ✅ SQLite fichier

Le projet utilise :

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
```

et NON :

```env
sqlite:///:memory:
```

---

# Pourquoi éviter SQLite mémoire

Le mode mémoire :

```txt
sqlite:///:memory:
```

est trop éloigné des contraintes réelles.

Il ne simule pas correctement :

- IO disque
- locks
- corruption fichier
- persistence réelle
- reconnexion
- erreurs filesystem
- taille DB
- comportements disque

Pour un projet orienté robustesse persistence :

```txt
ce n’est pas suffisamment réaliste
```

---

# Pourquoi SQLite fichier

SQLite fichier permet :

- vraie persistence
- vrai comportement disque
- vraie gestion IO
- vrais locks SQLite
- environnement simple
- exécution ultra rapide
- aucune dépendance externe
- CI simple
- zéro Docker obligatoire

C’est le meilleur compromis pour ce projet.

---

# Architecture des tests

L’architecture des tests suit exactement
l’architecture métier du projet.

```txt
tests/

├── ApiToken/
├── Dashboard/
├── Ingestion/
├── Integration/
├── Log/
├── Persistence/
├── Project/
├── Queue/
├── Search/
└── Shared/
```

---

# Règle principale

Les tests doivent refléter :

```txt
src/
```

et NON une architecture technique artificielle.

---

# Sous dossiers autorisés

Les sous dossiers métier et techniques réels sont autorisés.

Exemple :

```txt
tests/Persistence/

├── Application/
├── Domain/
└── Infrastructure/
```

car cela reflète :

```txt
src/Persistence/

├── Application/
├── Domain/
└── Infrastructure/
```

---

# Hiérarchie interdite

Éviter :

```txt
tests/Persistence/Integration/Database/Write/Sqlite/
```

ou :

```txt
tests/Unit/Persistence/Domain/
```

Car cela crée :

- surcharge mentale
- navigation difficile
- architecture floue
- séparation artificielle

---

# Crash tests

Les crash tests restent proches
du contexte métier concerné.

Exemple :

```txt
tests/Persistence/Infrastructure/

├── DoctrineLogWriterTest.php
├── DoctrineLogWriterCrashTest.php
├── SqliteFailureTest.php
└── CorruptedDatabaseTest.php
```

et NON :

```txt
tests/Crash/Persistence/
```

---

# Pourquoi cette architecture

Cette organisation permet :

- architecture miroir
- navigation immédiate
- responsabilité explicite
- maintenance simple
- cohérence avec `src/`
- isolation métier

Elle évite :

- hiérarchies profondes
- dossiers techniques inutiles
- séparation artificielle
- sur-ingénierie testing

---

# Tests unitaires

Les tests unitaires :

```txt
NE DOIVENT JAMAIS utiliser la DB
```

Ils testent uniquement :

- Domain
- ValueObjects
- Factory
- Fingerprint
- Normalizer
- logique métier pure

Utiliser uniquement :

```txt
mocks
stubs
fakes
```

---

# Tests d’intégration

Les tests d’intégration utilisent :

```txt
SQLite fichier
```

Ils testent :

- persistence
- repositories
- batch insert
- queue → persistence
- handlers
- transactions
- erreurs DB

---

# Crash tests

Les crash tests doivent simuler :

- DB indisponible
- fichier DB supprimé
- locks SQLite
- JSON invalide
- batch énorme
- disque plein
- corruption
- rollback
- données hostiles
- IO cassée

---

# Configuration PHPUnit

## phpunit.xml.dist

```xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit
    bootstrap="tests/bootstrap.php"
    cacheDirectory=".phpunit.cache"
    colors="true"
>

    <php>

        <server
            name="APP_ENV"
            value="test"
        />

        <server
            name="DATABASE_URL"
            value="sqlite:///%kernel.project_dir%/var/test.db"
        />

    </php>

    <testsuites>

        <testsuite name="Project Test Suite">

            <directory>
                tests
            </directory>

        </testsuite>

    </testsuites>

</phpunit>
```

---

# Configuration Doctrine

## config/packages/test/doctrine.yaml

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'

    orm:
        auto_generate_proxy_classes: true
```

---

# Base de test abstraite

Créer une classe de base :

```txt
tests/Shared/DatabaseTestCase.php
```

---

# Exemple DatabaseTestCase

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base de tests database.
 *
 * Responsabilités :
 * - démarrage kernel
 * - reset DB
 * - création schema
 * - isolation des tests
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();

        unset($this->entityManager);
    }

    /**
     * Recréation complète du schema.
     */
    private function resetDatabase(): void
    {
        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $tool = new SchemaTool($this->entityManager);

        $tool->dropSchema($metadata);

        $tool->createSchema($metadata);
    }
}
```

---

# Pourquoi utiliser SchemaTool

Le projet utilise :

```txt
SchemaTool
```

et NON :

```txt
migrations Doctrine
```

dans les tests.

---

# Raisons

SchemaTool est :

- plus rapide
- déterministe
- isolé
- simple
- sans dépendance historique

Les migrations servent :

```txt
à la production
```

pas aux tests.

---

# Exemple test integration

```php
<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Infrastructure;

use App\Tests\Shared\DatabaseTestCase;

final class DoctrineLogWriterTest extends DatabaseTestCase
{
    public function test_it_persists_log(): void
    {
        self::assertTrue(true);
    }
}
```

---

# Exemple crash test

```php
<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Infrastructure;

use App\Tests\Shared\DatabaseTestCase;

final class DoctrineLogWriterCrashTest extends DatabaseTestCase
{
    public function test_it_handles_closed_connection(): void
    {
        $this->entityManager
            ->getConnection()
            ->close();

        self::assertFalse(
            $this->entityManager
                ->getConnection()
                ->isConnected()
        );
    }
}
```

---

# Tests obligatoires

## Persistence nominale

Tester :

- insertion simple
- batch insert
- pagination
- lecture
- transactions

---

# Payloads hostiles

Tester :

- JSON invalide
- récursion
- payload énorme
- string gigantesque
- données sensibles
- UTF-8 cassé

---

# Erreurs infrastructure

Tester :

- DB inaccessible
- DB supprimée
- DB verrouillée
- rollback transaction
- exception SQL
- timeout
- corruption SQLite

---

# Exemple DB indisponible

```php
$this->entityManager
    ->getConnection()
    ->close();
```

Puis :

```php
$this->expectException(
    PersistenceException::class
);
```

---

# Exemple suppression DB

```php
unlink(
    self::getContainer()
        ->getParameter('kernel.project_dir')
    . '/var/test.db'
);
```

---

# Exemple batch massif

```php
for ($i = 0; $i < 10_000; ++$i) {
    // insert log
}
```

Le but :

```txt
tester stabilité mémoire
tester batch
tester persistence
tester temps d’écriture
```

---

# Règles importantes

## Toujours isoler les tests

Chaque test :

```txt
repart de zéro
```

Aucun état partagé.

---

# Jamais de DB réelle obligatoire

Les tests doivent fonctionner :

```txt
sans Docker
sans MySQL
sans PostgreSQL
```

---

# Quelques tests MySQL optionnels

Optionnellement :

```txt
quelques tests E2E critiques
```

peuvent être exécutés sur MySQL.

Mais cela ne doit PAS être la base de la suite de tests.

---

# Ce qui est interdit

## ❌ SQLite mémoire uniquement

Trop éloigné des contraintes réelles.

---

## ❌ DB partagée entre tests

Source d’instabilité.

---

## ❌ Tests dépendants de migrations

Fragiles et lents.

---

## ❌ Architecture de tests artificielle

Exemple interdit :

```txt
tests/Unit/Persistence/Domain/
```

ou :

```txt
tests/Persistence/Integration/Database/Write/
```

---

## ❌ Tester Doctrine lui-même

Doctrine est déjà testé.

Nous testons :

```txt
notre comportement
notre robustesse
notre architecture
```

---

# Résumé final

## Stratégie retenue

| Type | Solution |
|---|---|
| Unit | aucune DB |
| Integration | SQLite fichier |
| Crash tests | SQLite fichier |
| E2E critiques | MySQL optionnel |

---

# Objectif final

Garantir :

```txt
robustesse
résilience
stabilité
prédictibilité
```

même avec :

```txt
payloads hostiles
DB cassée
IO défaillante
batchs énormes
corruptions
```