<?php

declare(strict_types=1);

namespace App\ApiToken\Domain;

/**
 * Données minimales nécessaires
 * pour persister un token API hashé.
 */
final readonly class ApiTokenToStore
{
    public function __construct(
        private int $domainId,
        private string $tokenHash,
        private ?string $tokenPrefix,
        private string $label,
        private ?\DateTimeImmutable $expiresAt,
    ) {
        if ($this->domainId <= 0) {
            throw new \InvalidArgumentException(
                'Domain id must be positive.',
            );
        }

        if (trim($this->tokenHash) === '') {
            throw new \InvalidArgumentException(
                'Token hash cannot be empty.',
            );
        }

        if (trim($this->label) === '') {
            throw new \InvalidArgumentException(
                'Token label cannot be empty.',
            );
        }
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getTokenPrefix(): ?string
    {
        return $this->tokenPrefix;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
