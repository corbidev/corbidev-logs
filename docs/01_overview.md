
# 🧠 Vue globale du système de logs

## Objectif
Construire un système de logs :
- robuste (ne casse jamais)
- indépendant (pas de dépendance critique)
- sécurisé (normalization + filtrage)

## Authentification ingestion
- stratégie retenue : tokens opaques (Bearer)
- stockage : hash uniquement côté base
- état token : actif, révoqué, expiré
- JWT : non utilisé pour l'ingestion

## Philosophie
- un log imparfait vaut mieux qu’un log perdu
- aucune exception ne doit remonter
- une seule source de vérité : LogEntry
