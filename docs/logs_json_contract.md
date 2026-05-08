
# 📘 CONTRAT JSON LOGS — VERSION COMPLÈTE

## 🎯 OBJECTIF

Définir le format JSON officiel attendu par le système de logs.

Ce document sert de référence unique pour :
- les consommateurs externes
- l’API Logs
- le Shared Logging
- la persistence

---

# 🧠 PRINCIPES

- un log doit toujours être exploitable
- aucune donnée ne doit casser le système
- toutes les données sont normalisées
- le JSON final doit toujours être valide

---

# 🧱 STRUCTURE GLOBALE

```json
{
  "logs": []
}
```

---

# 📦 EXEMPLE COMPLET

```json
{
  "logs": [
    {
      "externalId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",

      "domain": "corbisier.fr",
      "uri": "/login",
      "method": "POST",
      "ip": "192.168.1.25",

      "message": "User login failed",
      "level": "ERROR",
      "env": "prod",

      "client": "web",
      "version": "1.0.0",

      "clientDate": "2026-05-08T14:12:32Z",
      "createdAt": "2026-05-08T14:12:33Z",

      "fingerprint": "5f2a1c8b9d3e4f1a",

      "userId": 42,
      "httpStatus": 401,

      "context": {
        "query": {
          "redirect": "/admin"
        },

        "target": {
          "domain": "auth.corbisier.fr",
          "uri": "/login"
        },

        "request": {
          "requestId": "req_8f5c1a",
          "userAgent": "Mozilla/5.0"
        },

        "security": {
          "firewall": "main",
          "authenticator": "LoginFormAuthenticator"
        },

        "debug": {
          "memoryMb": 12.4,
          "durationMs": 153
        }
      }
    }
  ]
}
```

---

# 🔴 CHAMPS OBLIGATOIRES STRICTS

## message
- string
- non vide

## level
Valeurs autorisées :
- INFO
- WARNING
- ERROR
- CRITICAL

## domain
Nom de domaine source.

## env
Exemple :
- prod
- dev
- test

## httpStatus
Code HTTP valide.

## client
Type de client :
- web
- mobile
- cli
- cron

---

# 🟠 CHAMPS OBLIGATOIRES AVEC CORRECTION

## externalId
UUID généré si absent ou invalide.

## createdAt
Date serveur UTC.

## clientDate
Date fournie client.

Si invalide :
→ remplacée par createdAt.

## uri
Si absent :
→ "/"

⚠ La query string est supprimée.

## ip
Si absente :
→ "0.0.0.0"

---

# 🟡 CHAMPS NORMALISÉS

## method
Fallback :
→ "UNKNOWN"

## version
Fallback :
→ "unknown"

---

# 🟢 CHAMPS OPTIONNELS

## userId
Identifiant utilisateur.

## context
Toujours un array JSON valide.

---

# 🔐 FINGERPRINT

## OBJECTIF

Regrouper les erreurs similaires.

---

## BASE

level|httpStatus|domain|uri|env

---

## NORMALISATION

- lowercase
- trim
- suppression query string

---

## HASH

- sha1
- tronqué à 16 caractères

---

## EXEMPLE

error|401|corbisier.fr|/login|prod

→ 5f2a1c8b9d3e4f1a

---

# 🌐 URI & QUERY STRING

## URI

Toujours :
- normalisée
- sans query string

Exemple :

/login?id=42

→

/login

---

## QUERY

Déplacée dans :

```json
"context": {
  "query": {}
}
```

---

# 🧼 CONTEXT & NORMALIZER

## OBJECTIF

Empêcher tout crash JSON.

---

## LIMITES

- profondeur max : 5
- éléments max : 50
- string max : 1000 caractères

---

## TYPES

| type | résultat |
|------|----------|
| object | "[object Class]" |
| recursion | "[circular]" |
| resource | "[resource]" |
| exception | structure sécurisée |

---

## FILTRAGE

Clés sensibles remplacées :

- password
- pwd
- token
- authorization
- cookie

→ "[FILTERED]"

---

# 🔥 EXEMPLE AVEC FALLBACK

## INPUT CLIENT

```json
{
  "logs": [
    {
      "message": "Fail",
      "level": "ERROR",
      "domain": "corbisier.fr",
      "env": "prod",
      "client": "web",
      "httpStatus": 500
    }
  ]
}
```

---

## APRÈS FACTORY

```json
{
  "logs": [
    {
      "externalId": "generated-uuid",

      "domain": "corbisier.fr",
      "uri": "/",
      "method": "UNKNOWN",
      "ip": "0.0.0.0",

      "message": "Fail",
      "level": "ERROR",
      "env": "prod",

      "client": "web",
      "version": "unknown",

      "clientDate": "2026-05-08T14:12:33Z",
      "createdAt": "2026-05-08T14:12:33Z",

      "fingerprint": "e2f4a9c5d8a1b7f3",

      "httpStatus": 500,

      "context": {}
    }
  ]
}
```

---

# 🧠 FLOW GLOBAL

```text
API JSON
   ↓
<<A CONSTRUIRE>>
```

---

# 🚀 CONCLUSION

Le JSON final garantit :

✔ stabilité  
✔ cohérence  
✔ sécurité  
✔ compatibilité persistence  
✔ compatibilité monitoring  
