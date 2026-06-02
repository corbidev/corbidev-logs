<?php

declare(strict_types=1);

namespace App\Persistence\Infrastructure\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity Doctrine représentant un log persisté.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - mapping SQL explicite
 * - stockage append-only
 * - structure stable long terme
 * - persistence optimisée écriture
 *
 * IMPORTANT :
 * ------------
 * Cette entity ne doit JAMAIS contenir :
 * - logique métier
 * - validation métier
 * - normalisation métier
 * - lifecycle callbacks
 * - services
 * - helpers métier
 * - relations Doctrine complexes
 *
 * PHILOSOPHIE :
 * -------------
 * Cette entity est volontairement :
 * - anémique
 * - prédictible
 * - simple
 * - optimisée écriture
 *
 * Le Domain reste :
 * - source de vérité
 * - immutable
 * - validé
 * - normalisé
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'logs',
    indexes: [
        new ORM\Index(
            name: 'idx_logs_domain_id',
            columns: ['domain_id'],
        ),

        new ORM\Index(
            name: 'idx_logs_created_at',
            columns: ['created_at'],
        ),

        new ORM\Index(
            name: 'idx_logs_fingerprint',
            columns: ['fingerprint'],
        ),

        new ORM\Index(
            name: 'idx_logs_request_id',
            columns: ['request_id'],
        ),

        new ORM\Index(
            name: 'idx_logs_level',
            columns: ['level'],
        ),

        new ORM\Index(
            name: 'idx_logs_env',
            columns: ['env'],
        ),

        new ORM\Index(
            name: 'idx_logs_http_status',
            columns: ['http_status'],
        ),

        new ORM\Index(
            name: 'idx_logs_domain_created',
            columns: ['domain_id', 'created_at'],
        ),

        new ORM\Index(
            name: 'idx_logs_domain_fingerprint',
            columns: ['domain_id', 'fingerprint'],
        ),

        new ORM\Index(
            name: 'idx_logs_domain_request',
            columns: ['domain_id', 'request_id'],
        ),
    ],

    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_logs_external_id',
            columns: ['external_id'],
        ),
    ],
)]
class LogRecord
{
    /**
     * Identifiant SQL auto-incrémenté.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: Types::BIGINT,
        options: [
            'unsigned' => true,
        ],
    )]
    private ?string $id = null;

    /**
     * UUID externe stable.
     */
    #[ORM\Column(
        name: 'external_id',
        type: Types::STRING,
        length: 36,
        unique: true,
    )]
    private string $externalId;

    /**
     * Domaine propriétaire.
     */
    #[ORM\Column(
        name: 'domain_id',
        type: Types::BIGINT,
        options: [
            'unsigned' => true,
        ],
    )]
    private string $domainId;

    /**
     * Fingerprint calculé serveur.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 16,
    )]
    private string $fingerprint;

    /**
     * RequestId normalisé.
     */
    #[ORM\Column(
        name: 'request_id',
        type: Types::STRING,
        length: 100,
    )]
    private string $requestId;

    /**
     * Niveau PSR-3.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 20,
    )]
    private string $level;

    /**
     * Code HTTP.
     */
    #[ORM\Column(
        name: 'http_status',
        type: Types::SMALLINT,
        options: [
            'unsigned' => true,
        ],
    )]
    private int $httpStatus;

    /**
     * Domaine applicatif.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 100,
    )]
    private string $domain;

    /**
     * URI normalisée.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 1000,
    )]
    private string $uri;

    /**
     * Méthode HTTP.
     *
     * Nullable :
     * - anciens payloads
     * - logs CLI
     * - logs non HTTP
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 10,
        nullable: true,
    )]
    private ?string $method = null;

    /**
     * User-Agent HTTP.
     *
     * Nullable :
     * - clients inconnus
     * - logs CLI
     * - ingestion partielle
     */
    #[ORM\Column(
        name: 'user_agent',
        type: Types::STRING,
        length: 500,
        nullable: true,
    )]
    private ?string $userAgent = null;

    /**
     * Environnement.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 50,
    )]
    private string $env;

    /**
     * Client source.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 50,
    )]
    private string $client;

    /**
     * Message principal.
     */
    #[ORM\Column(
        type: Types::TEXT,
    )]
    private string $message;

    /**
     * Contexte JSON.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(
        name: 'context_json',
        type: Types::JSON,
        options: [
            'jsonb' => false,
        ],
    )]
    private array $contextJson = [];

    /**
     * Données extra JSON.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(
        name: 'extra_json',
        type: Types::JSON,
        options: [
            'jsonb' => false,
        ],
    )]
    private array $extraJson = [];

    /**
     * Warnings ingestion JSON.
     *
     * Contient :
     * - corrections automatiques
     * - champs tronqués
     * - payloads invalides corrigés
     * - normalisations forcées
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(
        name: 'ingestion_warnings_json',
        type: Types::JSON,
        options: [
            'jsonb' => false,
        ],
    )]
    private array $ingestionWarningsJson = [];

    /**
     * Date serveur.
     */
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
    )]
    private \DateTimeImmutable $createdAt;

    /**
     * Date client.
     */
    #[ORM\Column(
        name: 'client_date',
        type: Types::DATETIME_IMMUTABLE,
        nullable: true,
    )]
    private ?\DateTimeImmutable $clientDate = null;

    /**
     * Adresse IP.
     *
     * Nullable :
     * - logs CLI
     * - logs système
     * - ingestion partielle
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 45,
        nullable: true,
    )]
    private ?string $ip = null;

    /**
     * Retourne l'identifiant SQL.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Retourne l'identifiant externe.
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * Définit l'identifiant externe.
     */
    public function setExternalId(
        string $externalId,
    ): void {
        $this->externalId = $externalId;
    }

    /**
     * Retourne le domaine.
     */
    public function getDomainId(): string
    {
        return $this->domainId;
    }

    /**
     * Définit le domaine.
     */
    public function setDomainId(
        string $domainId,
    ): void {
        $this->domainId = $domainId;
    }

    /**
     * Retourne le fingerprint.
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Définit le fingerprint.
     */
    public function setFingerprint(
        string $fingerprint,
    ): void {
        $this->fingerprint = $fingerprint;
    }

    /**
     * Retourne le requestId.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Définit le requestId.
     */
    public function setRequestId(
        string $requestId,
    ): void {
        $this->requestId = $requestId;
    }

    /**
     * Retourne le niveau.
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Définit le niveau.
     */
    public function setLevel(
        string $level,
    ): void {
        $this->level = $level;
    }

    /**
     * Retourne le status HTTP.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Définit le status HTTP.
     */
    public function setHttpStatus(
        int $httpStatus,
    ): void {
        $this->httpStatus = $httpStatus;
    }

    /**
     * Retourne le domaine.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Définit le domaine.
     */
    public function setDomain(
        string $domain,
    ): void {
        $this->domain = $domain;
    }

    /**
     * Retourne l'URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Définit l'URI.
     */
    public function setUri(
        string $uri,
    ): void {
        $this->uri = $uri;
    }

    /**
     * Retourne la méthode HTTP.
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * Définit la méthode HTTP.
     */
    public function setMethod(
        ?string $method,
    ): void {
        $this->method = $method;
    }

    /**
     * Retourne le User-Agent.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Définit le User-Agent.
     */
    public function setUserAgent(
        ?string $userAgent,
    ): void {
        $this->userAgent = $userAgent;
    }

    /**
     * Retourne l'environnement.
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * Définit l'environnement.
     */
    public function setEnv(
        string $env,
    ): void {
        $this->env = $env;
    }

    /**
     * Retourne le client.
     */
    public function getClient(): string
    {
        return $this->client;
    }

    /**
     * Définit le client.
     */
    public function setClient(
        string $client,
    ): void {
        $this->client = $client;
    }

    /**
     * Retourne le message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Définit le message.
     */
    public function setMessage(
        string $message,
    ): void {
        $this->message = $message;
    }

    /**
     * Retourne le contexte JSON.
     *
     * @return array<string, mixed>
     */
    public function getContextJson(): array
    {
        return $this->contextJson;
    }

    /**
     * Définit le contexte JSON.
     *
     * @param array<string, mixed> $contextJson
     */
    public function setContextJson(
        array $contextJson,
    ): void {
        $this->contextJson = $contextJson;
    }

    /**
     * Retourne les données extra JSON.
     *
     * @return array<string, mixed>
     */
    public function getExtraJson(): array
    {
        return $this->extraJson;
    }

    /**
     * Définit les données extra JSON.
     *
     * @param array<string, mixed> $extraJson
     */
    public function setExtraJson(
        array $extraJson,
    ): void {
        $this->extraJson = $extraJson;
    }

    /**
     * Retourne les warnings ingestion JSON.
     *
     * @return list<array<string, mixed>>
     */
    public function getIngestionWarningsJson(): array
    {
        return $this->ingestionWarningsJson;
    }

    /**
     * Définit les warnings ingestion JSON.
     *
     * @param list<array<string, mixed>> $ingestionWarningsJson
     */
    public function setIngestionWarningsJson(
        array $ingestionWarningsJson,
    ): void {
        $this->ingestionWarningsJson = $ingestionWarningsJson;
    }

    /**
     * Retourne la date serveur.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Définit la date serveur.
     */
    public function setCreatedAt(
        \DateTimeImmutable $createdAt,
    ): void {
        $this->createdAt = $createdAt;
    }

    /**
     * Retourne la date client.
     */
    public function getClientDate(): ?\DateTimeImmutable
    {
        return $this->clientDate;
    }

    /**
     * Définit la date client.
     */
    public function setClientDate(
        ?\DateTimeImmutable $clientDate,
    ): void {
        $this->clientDate = $clientDate;
    }

    /**
     * Retourne l'adresse IP.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * Définit l'adresse IP.
     */
    public function setIp(
        ?string $ip,
    ): void {
        $this->ip = $ip;
    }
}