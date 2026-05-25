<?php

declare(strict_types=1);

namespace App\Dashboard\Domain;

/**
 * Vue de détail d'un log côté Dashboard.
 */
final readonly class DashboardLogDetailsView
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     * @param list<string> $ingestionWarnings
     */
    public function __construct(
        private string $externalId,
        private int $projectId,
        private string $fingerprint,
        private string $requestId,
        private string $level,
        private int $httpStatus,
        private string $domain,
        private string $uri,
        private ?string $method,
        private ?string $userAgent,
        private string $env,
        private string $client,
        private string $message,
        private array $context,
        private array $extra,
        private array $ingestionWarnings,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $clientDate,
        private ?string $ip,
    ) {
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function getClient(): string
    {
        return $this->client;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @return list<string>
     */
    public function getIngestionWarnings(): array
    {
        return $this->ingestionWarnings;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getClientDate(): ?\DateTimeImmutable
    {
        return $this->clientDate;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }
}