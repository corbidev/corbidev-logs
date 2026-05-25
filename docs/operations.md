# Operations

## Queue consumer

Commande standard:

```bash
cd logs
php bin/console app:queue:process
```

Commande avec limite:

```bash
cd logs
php bin/console app:queue:process 200
```

Metriques a surveiller:

- Processed
- Failed
- MovedToFailed
- Retries
- Duration

Repertoires queue:

```txt
logs/var/queue/
├── logs/
├── processing/
├── corrupted/
└── failed/
```

## Persistence SQL

Objectif: persister les logs normalises sans casser le pipeline.

Regles:

- batchs bornes
- erreurs explicites
- rollback maitrise

## Strategie de tests DB

Le projet privilegie SQLite fichier pour les tests d'integration:

- meilleur realisme I/O que sqlite memoire
- execution rapide sans service externe

Commandes:

```bash
cd logs
php bin/phpunit tests/Persistence
php bin/phpunit
```
