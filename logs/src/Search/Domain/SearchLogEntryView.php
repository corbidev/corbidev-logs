<?php

declare(strict_types=1);

namespace App\Search\Domain;

/**
 * Vue de lecture minimale d'un log.
 */
final readonly class SearchLogEntryView
{
    public function __construct(
        private int $id,
        private string $externalId,
        private int $projectId,
        private string $level,
        private string $domain,
        private string $message,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
