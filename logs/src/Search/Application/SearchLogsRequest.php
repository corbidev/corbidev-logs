<?php

declare(strict_types=1);

namespace App\Search\Application;

/**
 * Requête de recherche paginée.
 */
final readonly class SearchLogsRequest
{
    public function __construct(
        private int $page,
        private int $perPage,
        private ?\DateTimeImmutable $fromDate = null,
        private ?\DateTimeImmutable $toDate = null,
        private ?string $level = null,
        private ?string $domain = null,
        private ?int $domainId = null,
        private ?string $fingerprint = null,
        private ?string $query = null,
    ) {
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getFromDate(): ?\DateTimeImmutable
    {
        return $this->fromDate;
    }

    public function getToDate(): ?\DateTimeImmutable
    {
        return $this->toDate;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getDomainId(): ?int
    {
        return $this->domainId;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }
}
