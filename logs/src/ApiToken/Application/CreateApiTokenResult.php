<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

/**
 * Résultat de création d'un token API.
 */
final readonly class CreateApiTokenResult
{
    public function __construct(
        private string $plainToken,
        private string $tokenPrefix,
    ) {
    }

    public function getPlainToken(): string
    {
        return $this->plainToken;
    }

    public function getTokenPrefix(): string
    {
        return $this->tokenPrefix;
    }
}
