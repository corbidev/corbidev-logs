<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

/**
 * Requête de validation d'un token API.
 */
final readonly class ValidateApiTokenRequest
{
    private string $plainToken;

    public function __construct(
        string $plainToken,
        private ?\DateTimeImmutable $now = null,
    ) {
        $this->plainToken = trim($plainToken);

        if ($this->plainToken === '') {
            throw new \InvalidArgumentException(
                'Plain token cannot be empty.',
            );
        }
    }

    public function getPlainToken(): string
    {
        return $this->plainToken;
    }

    public function getNow(): \DateTimeImmutable
    {
        return $this->now ?? new \DateTimeImmutable('now');
    }
}
