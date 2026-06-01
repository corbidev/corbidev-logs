<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

/**
 * Requête de création d'un token API.
 */
final readonly class CreateApiTokenRequest
{
    private string $label;

    public function __construct(
        private int $domainId,
        string $label,
        private ?\DateTimeImmutable $expiresAt = null,
    ) {
        if ($this->domainId <= 0) {
            throw new \InvalidArgumentException(
                'Domain id must be positive.',
            );
        }

        $this->label = $this->sanitizeLabel($label);
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    private function sanitizeLabel(string $label): string
    {
        $label = trim($label);

        if ($label === '') {
            throw new \InvalidArgumentException(
                'Token label cannot be empty.',
            );
        }

        return mb_substr(
            $label,
            0,
            150,
        );
    }
}
