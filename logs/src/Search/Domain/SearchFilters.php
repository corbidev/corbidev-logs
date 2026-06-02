<?php

declare(strict_types=1);

namespace App\Search\Domain;

/**
 * Filtres combinables de Search.
 */
final readonly class SearchFilters
{
    public function __construct(
        private ?\DateTimeImmutable $fromDate = null,
        private ?\DateTimeImmutable $toDate = null,
        private ?string $level = null,
        private ?string $domain = null,
        private ?int $domainId = null,
        private ?string $fingerprint = null,
        private ?string $query = null,
    ) {
        if ($this->fromDate !== null && $this->toDate !== null && $this->fromDate > $this->toDate) {
            throw new \InvalidArgumentException(
                'Search from date cannot be greater than to date.',
            );
        }

        if ($this->domainId !== null && $this->domainId <= 0) {
            throw new \InvalidArgumentException(
                'Search domain id must be positive.',
            );
        }
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
        return $this->normalizeNullableString(
            $this->level,
        );
    }

    public function getDomain(): ?string
    {
        return $this->normalizeNullableString(
            $this->domain,
        );
    }

    public function getDomainId(): ?int
    {
        return $this->domainId;
    }

    public function getFingerprint(): ?string
    {
        return $this->normalizeNullableString(
            $this->fingerprint,
        );
    }

    public function getQuery(): ?string
    {
        return $this->normalizeNullableString(
            $this->query,
        );
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_strtolower(
            mb_substr($value, 0, 100),
        );
    }
}
