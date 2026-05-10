<?php

declare(strict_types=1);

namespace App\Log\Domain\Entity;

use App\Log\Domain\Exception\InvalidLogEntryException;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Tags;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Représente un log immutable valide.
 *
 * Source de vérité du système.
 *
 * Responsabilités :
 * - encapsuler un événement de log complet
 * - garantir les invariants métier
 * - fournir une structure stable
 * - protéger le domaine des données hostiles
 *
 * Invariants :
 * - toujours valide
 * - immutable
 * - données normalisées
 * - message jamais vide
 * - fingerprint toujours présent
 */
final readonly class LogEntry
{
    /**
     * Taille maximale du message.
     */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Identifiant unique externe.
     */
    private string $id;

    /**
     * Message principal.
     */
    private string $message;

    /**
     * Niveau de log.
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
     * Adresse IP.
     */
    private IpAddress $ipAddress;

    /**
     * Fingerprint serveur.
     */
    private Fingerprint $fingerprint;

    /**
     * Tags normalisés.
     */
    private Tags $tags;

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
        Tags $tags,
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

        $this->guardMessage($message);

        $this->guardDomain($domain);

        $this->message = $message;
        $this->level = $level;
        $this->domain = $domain;
        $this->environment = $environment;
        $this->httpStatus = $httpStatus;
        $this->client = $client;
        $this->request = $request;
        $this->ipAddress = $ipAddress;
        $this->fingerprint = $fingerprint;
        $this->tags = $tags;
        $this->context = $context;
        $this->extra = $extra;
        $this->clientDate = $clientDate;
        $this->createdAt = $createdAt
            ?? new DateTimeImmutable();

        $this->id = $id
            ?? Uuid::v7()->toRfc4122();
    }

    /**
     * Retourne l'identifiant unique.
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
     * Retourne la requête.
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
     * Retourne les tags.
     */
    public function tags(): Tags
    {
        return $this->tags;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
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
     * Vérifie si le log est une erreur.
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
     * Snapshot sérialisable.
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
            'uri' => $this->request->uri()->value(),
            'method' => $this->request->method(),
            'userAgent' => $this->request->userAgent(),
            'ip' => $this->ipAddress->value(),
            'fingerprint' => $this->fingerprint->value(),
            'tags' => $this->tags->values(),
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
     * @throws InvalidLogEntryException
     */
    private function guardDomain(
        string $domain,
    ): void {
        if ($domain === '') {
            throw InvalidLogEntryException::emptyDomain();
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