# Contrat API ingestion

## Endpoint

- POST `/api/logs`
- JSON uniquement
- Bearer token opaque obligatoire

## Headers requis

- `Content-Type: application/json`
- `Authorization: Bearer <token_opaque>`

## Payload minimal

```json
{
  "logs": [
    {
      "message": "Erreur paiement",
      "level": "error",
      "domain": "billing",
      "env": "prod",
      "httpStatus": 500,
      "client": "api"
    }
  ]
}
```

## Champs minimaux

- message
- level
- domain
- env
- httpStatus
- client

## Champs derives

- externalId
- requestId
- createdAt
- fingerprint

## Erreurs standards

- `unsupported_media_type` (415)
- `invalid_json` (400)
- `invalid_payload` (400)
- `unauthorized` (401)

## Reponse succes

- HTTP `202 Accepted`
- JSON stable avec compteur `received`
