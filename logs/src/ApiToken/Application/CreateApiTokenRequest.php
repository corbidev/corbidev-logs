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
        private int $projectId,
        string $label,
        private ?\DateTimeImmutable $expiresAt = null,
    ) {
        if ($this->projectId <= 0) {
            throw new \InvalidArgumentException(
                'Project id must be positive.',
            );
        }

        $this->label = $this->sanitizeLabel($label);
    }

    public function getProjectId(): int
    {
        return $this->projectId;
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
