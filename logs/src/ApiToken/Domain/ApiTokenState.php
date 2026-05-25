<?php

declare(strict_types=1);

namespace App\ApiToken\Domain;

/**
 * État d'acceptation d'un token API.
 */
enum ApiTokenState: string
{
    case ACTIVE = 'active';

    case REVOKED = 'revoked';

    case EXPIRED = 'expired';

    case NOT_FOUND = 'not_found';
}
