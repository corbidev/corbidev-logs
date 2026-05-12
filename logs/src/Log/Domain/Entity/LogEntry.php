<?php

declare(strict_types=1);

namespace App\Log\Domain\Entity;

use App\Log\Domain\Exception\InvalidLogEntryException;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
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
 * - aucune dépendance Symfony métier
 * - aucun état partiel
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
    private string $id;

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
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null,
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

        $this->id = $this->buildId(
            $id,
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
        $this->context = $context;
        $this->extra = $extra;
        $this->clientDate = $clientDate;
        $this->createdAt = $createdAt
            ?? new DateTimeImmutable();
    }

    /**
     * Retourne l'identifiant externe.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Retourne le message.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Retourne le niveau.
     */
    public function level(): LogLevel
    {
        return $this->level;
    }

    /**
     * Retourne le domaine.
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Retourne l'environnement.
     */
    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * Retourne le status HTTP.
     */
    public function httpStatus(): HttpStatus
    {
        return $this->httpStatus;
    }

    /**
     * Retourne le client.
     */
    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Retourne la requête HTTP.
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Retourne l'adresse IP.
     */
    public function ipAddress(): IpAddress
    {
        return $this->ipAddress;
    }

    /**
     * Retourne le fingerprint.
     */
    public function fingerprint(): Fingerprint
    {
        return $this->fingerprint;
    }

    /**
     * Retourne le contexte.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Retourne les données supplémentaires.
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * Retourne la date serveur.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Retourne la date client.
     */
    public function clientDate(): ?DateTimeImmutable
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
        return $this->id === $other->id;
    }

    /**
     * Retourne une représentation sérialisable stable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'level' => $this->level->value,
            'domain' => $this->domain,
            'environment' => $this->environment->value,
            'httpStatus' => $this->httpStatus->value(),
            'client' => $this->client->value(),
            'method' => $this->request->method(),
            'uri' => $this->request->uri()->value(),
            'userAgent' => $this->request->userAgent(),
            'ip' => $this->ipAddress->value(),
            'fingerprint' => $this->fingerprint->value(),
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
    private function buildId(
        ?string $id,
    ): string {
        $id = trim(
            (string) $id,
        );

        if ($id !== '') {
            return $id;
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
     * Normalise le message.
     */
    private function normalizeMessage(
        string $message,
    ): string {
        return trim($message);
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