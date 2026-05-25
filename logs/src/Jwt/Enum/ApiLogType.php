<?php

namespace App\Jwt\Enum;

enum ApiLogType: string
{
    case INVALID_SIGNATURE = 'invalid_signature';
    case REPLAY = 'replay';
    case EXPIRED = 'expired';
    case UNKNOWN_CLIENT = 'unknown_client';
    case MISSING_HEADERS = 'missing_headers';
    case INVALID_TIMESTAMP = 'invalid_timestamp';

    /**
     * Libellé lisible (debug / affichage)
     */
    public function label(): string
    {
        return match ($this) {
            self::INVALID_SIGNATURE => 'Signature HMAC invalide',
            self::REPLAY => 'Requête rejouée (nonce déjà utilisé)',
            self::EXPIRED => 'Requête expirée',
            self::UNKNOWN_CLIENT => 'Client inconnu',
            self::MISSING_HEADERS => 'Headers HMAC manquants',
            self::INVALID_TIMESTAMP => 'Timestamp invalide',
        };
    }
}
