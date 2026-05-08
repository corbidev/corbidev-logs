
# 📦 Contrat LogEntry

## Obligatoires strict
- message
- level (INFO, WARNING, ERROR, CRITICAL)
- domain
- env
- httpStatus
- client

## Obligatoires auto-fix
- externalId (uuid généré)
- createdAt (date serveur)
- clientDate (fallback createdAt)
- uri ("/")
- ip ("0.0.0.0")

## Optionnels
- userId
- context

## Règles fortes
- aucun LogEntry incomplet
- aucune donnée non contrôlée
