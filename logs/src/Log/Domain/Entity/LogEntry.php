<?php

declare(strict_types=1);

namespace App\Log\Domain\Entity;

use App\Log\Domain\Exception\InvalidLogEntryException;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IngestionWarning;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Représente un log immutable valide.
 *
 * SOURCE DE VÉRITÉ :
 * ------------------
 * LogEntry représente l'événement métier
 * central du système de logs.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - encapsuler un log valide
 * - protéger les invariants métier
 * - garantir une structure stable
 * - fournir une représentation immutable
 * - empêcher les états incohérents
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - simplicité
 * - prédictibilité
 * - stabilité long terme
 *
 * GARANTIES :
 * -----------
 * - toujours valide
 * - immutable
 * - message normalisé
 * - domaine normalisé
 * - fingerprint toujours présent
 * - requestId toujours présent
 * - aucune dépendance Symfony métier
 * - aucun état partiel
 *
 * IMPORTANT :
 * ------------
 * LogEntry représente l'état FINAL valide
 * après ingestion et normalisation.
 *
 * Les anomalies ingestion éventuelles
 * sont conservées dans ingestionWarnings.
 */
final readonly class LogEntry
{
    /**
     * Taille maximale du message.
     */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Taille maximale du domaine.
     */
    private const MAX_DOMAIN_LENGTH = 100;

    /**
     * Identifiant externe unique.
     */
    private string $externalId;


    /**
     * Message principal.
     */
    private string $message;

    /**
     * Niveau du log.
     */
    private LogLevel $level;

    /**
     * Domaine applicatif.
     */
    private string $domain;

    /**
     * Environnement applicatif.
     */
    private Environment $environment;

    /**
     * Status HTTP.
     */
    private HttpStatus $httpStatus;

    /**
     * Client source.
     */
    private Client $client;

    /**
     * Identifiant de corrélation requête.
     */
    private RequestId $requestId;

    /**
     * Requête HTTP.
     */
    private Request $request;

    /**
     * Adresse IP source.
     */
    private IpAddress $ipAddress;

    /**
     * Fingerprint serveur.
     */
    private Fingerprint $fingerprint;

    /**
     * Warnings ingestion.
     *
     * @var list<IngestionWarning>
     */
    private array $ingestionWarnings;

    /**
     * Contexte libre.
     *
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * Données supplémentaires.
     *
     * @var array<string, mixed>
     */
    private array $extra;

    /**
     * Date de création serveur.
     */
    private DateTimeImmutable $createdAt;

    /**
     * Date client optionnelle.
     */
    private ?DateTimeImmutable $clientDate;

    /**
     * @param list<IngestionWarning> $ingestionWarnings
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     *
     * @throws InvalidLogEntryException
     */
    public function __construct(
        string $message,
        LogLevel $level,
        string $domain,
        Environment $environment,
        HttpStatus $httpStatus,
        Client $client,
        Request $request,
        IpAddress $ipAddress,
        Fingerprint $fingerprint,
        RequestId $requestId,
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $externalId = null,
    ) {

        $message = $this->normalizeMessage(
            $message,
        );

        $domain = $this->normalizeDomain(
            $domain,
        );

        $this->guardMessage(
            $message,
        );

        $this->guardDomain(
            $domain,
        );

        $this->guardWarnings(
            $ingestionWarnings,
        );

        $this->externalId = $this->buildExternalId(
            $externalId,
        );

        $this->message = $message;
        $this->level = $level;
        $this->domain = $domain;
        $this->environment = $environment;
        $this->httpStatus = $httpStatus;
        $this->client = $client;
        $this->request = $request;
        $this->ipAddress = $ipAddress;
        $this->fingerprint = $fingerprint;
        $this->requestId = $requestId;
        $this->ingestionWarnings = $ingestionWarnings;
        $this->context = $context;
        $this->extra = $extra;
        $this->clientDate = $clientDate;
        $this->createdAt = $createdAt
            ?? new DateTimeImmutable();
    }

        /**
     * Retourne l'identifiant externe.
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * Retourne le message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Retourne le niveau.
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Retourne le domaine.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Retourne l'environnement.
     */
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * Retourne le status HTTP.
     */
    public function getHttpStatus(): HttpStatus
    {
        return $this->httpStatus;
    }

    /**
     * Retourne le client.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Retourne l'identifiant de requête.
     */
    public function getRequestId(): RequestId
    {
        return $this->requestId;
    }

    /**
     * Retourne la requête HTTP.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Retourne l'adresse IP.
     */
    public function getIpAddress(): IpAddress
    {
        return $this->ipAddress;
    }

    /**
     * Retourne le fingerprint.
     */
    public function getFingerprint(): Fingerprint
    {
        return $this->fingerprint;
    }

    /**
     * Retourne les warnings ingestion.
     *
     * @return list<IngestionWarning>
     */
    public function getIngestionWarnings(): array
    {
        return $this->ingestionWarnings;
    }

    /**
     * Vérifie la présence de warnings ingestion.
     */
    public function hasIngestionWarnings(): bool
    {
        return $this->ingestionWarnings !== [];
    }

    /**
     * Retourne le contexte.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Retourne les données supplémentaires.
     *
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Retourne la date serveur.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Retourne la date client.
     */
    public function getClientDate(): ?DateTimeImmutable
    {
        return $this->clientDate;
    }

    /**
     * Vérifie si le log représente une erreur.
     */
    public function isError(): bool
    {
        return $this->level->isError()
            || $this->httpStatus->isError();
    }

    /**
     * Compare deux logs.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->externalId === $other->externalId;
    }

    /**
     * Retourne une représentation sérialisable stable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,

            'message' => $this->message,

            'level' => $this->level->value,

            'domain' => $this->domain,

            'environment' => $this->environment->value,

            'httpStatus' => $this->httpStatus->value(),

            'client' => $this->client->value(),

            'requestId' => $this->requestId->value(),

            'request' => [
                'method' => $this->request->method(),
                'uri' => $this->request->uri()->value(),
                'userAgent' => $this->request->userAgent(),
            ],

            'ip' => $this->ipAddress->value(),

            'fingerprint' => $this->fingerprint->value(),

            'ingestionWarnings' => array_map(
                static fn (
                    IngestionWarning $warning,
                ): array => $warning->toArray(),
                $this->ingestionWarnings,
            ),

            'context' => $this->context,

            'extra' => $this->extra,

            'createdAt' => $this->createdAt->format(
                DATE_ATOM,
            ),

            'clientDate' => $this->clientDate?->format(
                DATE_ATOM,
            ),
        ];
    }

    /**
     * Construit un identifiant stable.
     */
    private function buildExternalId(
        ?string $externalId,
    ): string {
        $externalId = trim(
            (string) $externalId,
        );

        if ($externalId !== '') {
            return $externalId;
        }

        return Uuid::v7()->toRfc4122();
    }

    /**
     * Vérifie le message.
     *
     * @throws InvalidLogEntryException
     */
    private function guardMessage(
        string $message,
    ): void {
        if ($message === '') {
            throw InvalidLogEntryException::emptyMessage();
        }

        if (
            mb_strlen($message)
            > self::MAX_MESSAGE_LENGTH
        ) {
            throw InvalidLogEntryException::messageTooLong(
                self::MAX_MESSAGE_LENGTH,
            );
        }
    }

    /**
     * Vérifie le domaine.
     *
     * @throws InvalidLogEntryException
     */
    private function guardDomain(
        string $domain,
    ): void {
        if ($domain === '') {
            throw InvalidLogEntryException::emptyDomain();
        }

        if (
            mb_strlen($domain)
            > self::MAX_DOMAIN_LENGTH
        ) {
            throw InvalidLogEntryException::domainTooLong(
                self::MAX_DOMAIN_LENGTH,
            );
        }
    }

    /**
     * Vérifie les warnings ingestion.
     *
     * @param list<mixed> $warnings
     *
     * @throws InvalidLogEntryException
     */
    private function guardWarnings(
        array $warnings,
    ): void {
        foreach ($warnings as $warning) {
            if (
                $warning instanceof IngestionWarning
                === false
            ) {
                throw InvalidLogEntryException::invalidContext();
            }
        }
    }

    /**
     * Normalise le message.
     */
    private function normalizeMessage(
        string $message,
    ): string {
        return trim(
            $message,
        );
    }

    /**
     * Normalise le domaine.
     */
    private function normalizeDomain(
        string $domain,
    ): string {
        return strtolower(
            trim($domain),
        );
    }
}