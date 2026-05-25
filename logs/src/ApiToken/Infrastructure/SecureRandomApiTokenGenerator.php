<?php

declare(strict_types=1);

namespace App\ApiToken\Infrastructure;

use App\ApiToken\Domain\ApiTokenGeneratorInterface;

/**
 * Générateur de tokens opaques
 * basé sur random_bytes.
 */
final class SecureRandomApiTokenGenerator implements ApiTokenGeneratorInterface
{
    public function generate(): string
    {
        return sprintf(
            'cbi_%s',
            bin2hex(random_bytes(32)),
        );
    }
}
