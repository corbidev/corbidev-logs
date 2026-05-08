
# 🔐 Fingerprint

## Objectif
Regrouper les logs similaires

## Base
level|httpStatus|domain|uri|env

## Normalisation
- lowercase
- trim
- suppression query string

## Hash
- sha1 tronqué (16 chars)

## Règle
toujours recalculé serveur
