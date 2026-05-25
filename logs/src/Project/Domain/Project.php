<?php

declare(strict_types=1);

namespace App\Project\Domain;

/**
 * Modèle métier initial d'un projet logique.
 */
final readonly class Project
{
    private string $slug;

    private string $name;

    public function __construct(
        private int $id,
        string $slug,
        string $name,
        private int $retentionDays,
        private bool $isActive,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(
                'Project id must be positive.',
            );
        }

        $this->slug = $this->sanitizeSlug($slug);
        $this->name = $this->sanitizeName($name);

        if ($this->retentionDays <= 0) {
            throw new \InvalidArgumentException(
                'Project retention days must be greater than zero.',
            );
        }
    }

    public static function default(): self
    {
        $now = new \DateTimeImmutable('now');

        return new self(
            id: 1,
            slug: 'default',
            name: 'Default project',
            retentionDays: 30,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new \InvalidArgumentException(
                'Project slug cannot be empty.',
            );
        }

        return mb_substr(
            mb_strtolower($slug),
            0,
            100,
        );
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(
                'Project name cannot be empty.',
            );
        }

        return mb_substr(
            $name,
            0,
            150,
        );
    }
}
