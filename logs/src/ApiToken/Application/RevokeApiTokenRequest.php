<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

/**
 * Requête de révocation d'un token API.
 */
final readonly class RevokeApiTokenRequest
{
    private string $plainToken;

    public function __construct(
        string $plainToken,
        private ?\DateTimeImmutable $revokedAt = null,
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

    public function getRevokedAt(): \DateTimeImmutable
    {
        return $this->revokedAt ?? new \DateTimeImmutable('now');
    }
}
