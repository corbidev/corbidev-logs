<?php

declare(strict_types=1);

namespace App\ApiToken\Infrastructure;

use App\ApiToken\Domain\ApiTokenHasherInterface;

/**
 * Hash de token opaque via SHA-256.
 */
final class Sha256ApiTokenHasher implements ApiTokenHasherInterface
{
    public function hash(string $plainToken): string
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            throw new \InvalidArgumentException(
                'Plain token cannot be empty.',
            );
        }

        return hash(
            'sha256',
            $plainToken,
        );
    }
}
