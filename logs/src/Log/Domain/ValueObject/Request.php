<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidRequestException;

/**
 * Représente une requête HTTP normalisée.
 *
 * Responsabilités :
 * - encapsuler les données HTTP utiles
 * - stabiliser les données de recherche
 * - normaliser les données externes hostiles
 * - fournir un snapshot HTTP immutable
 *
 * Invariants :
 * - méthode HTTP valide
 * - URI toujours valide
 * - user-agent borné
 * - données normalisées
 * - immutable
 */
final readonly class Request
{
    /**
     * Méthodes HTTP supportées.
     *
     * @var list<string>
     */
    private const ALLOWED_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'HEAD',
        'OPTIONS',
    ];

    /**
     * Longueur maximale méthode HTTP.
     */
    private const MAX_METHOD_LENGTH = 10;

    /**
     * Longueur maximale User-Agent.
     */
    private const MAX_USER_AGENT_LENGTH = 500;

    /**
     * URI HTTP normalisée.
     */
    private Uri $uri;

    /**
     * Méthode HTTP normalisée.
     */
    private string $method;

    /**
     * User-Agent normalisé.
     */
    private string $userAgent;

    /**
     * @throws InvalidRequestException
     */
    public function __construct(
        Uri $uri,
        string $method = 'GET',
        string $userAgent = '',
    ) {
        $method = $this->normalizeMethod(
            $method,
        );

        $userAgent = $this->normalizeUserAgent(
            $userAgent,
        );

        $this->guardMethod($method);

        $this->guardUserAgent($userAgent);

        $this->uri = $uri;
        $this->method = $method;
        $this->userAgent = $userAgent;
    }

    /**
     * Crée une Request depuis
     * des données externes hostiles.
     *
     * Règles :
     * - aucune exception
     * - fallback sécurisé
     * - normalisation agressive
     */
    public static function fromExternal(
        mixed $uri,
        mixed $method = null,
        mixed $userAgent = null,
    ): self {
        $normalizedUri = Uri::fromExternal(
            $uri,
        );

        $method = is_string($method)
            ? $method
            : 'GET';

        $userAgent = is_string($userAgent)
            ? $userAgent
            : '';

        try {
            return new self(
                $normalizedUri,
                $method,
                $userAgent,
            );
        } catch (InvalidRequestException) {
            return new self(
                new Uri('/'),
                'GET',
                '',
            );
        }
    }

    /**
     * Retourne l'URI.
     */
    public function uri(): Uri
    {
        return $this->uri;
    }

    /**
     * Retourne la méthode HTTP.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Retourne le User-Agent.
     */
    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Vérifie si la méthode est GET.
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Vérifie si la méthode est POST.
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Compare deux requêtes.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->uri->equals(
            $other->uri,
        )
        && $this->method === $other->method
        && $this->userAgent === $other->userAgent;
    }

    /**
     * Représentation string stable.
     */
    public function __toString(): string
    {
        return sprintf(
            '%s %s',
            $this->method,
            $this->uri->value(),
        );
    }

    /**
     * @throws InvalidRequestException
     */
    private function guardMethod(
        string $method,
    ): void {
        if ($method === '') {
            throw InvalidRequestException::emptyMethod();
        }

        if (
            mb_strlen($method)
            > self::MAX_METHOD_LENGTH
        ) {
            throw InvalidRequestException::methodTooLong(
                self::MAX_METHOD_LENGTH,
            );
        }

        if (
            in_array(
                $method,
                self::ALLOWED_METHODS,
                true,
            ) === false
        ) {
            throw InvalidRequestException::invalidMethod();
        }
    }

    /**
     * @throws InvalidRequestException
     */
    private function guardUserAgent(
        string $userAgent,
    ): void {
        if (
            mb_strlen($userAgent)
            > self::MAX_USER_AGENT_LENGTH
        ) {
            throw InvalidRequestException::userAgentTooLong(
                self::MAX_USER_AGENT_LENGTH,
            );
        }
    }

    /**
     * Normalise une méthode HTTP.
     */
    private function normalizeMethod(
        string $method,
    ): string {
        return strtoupper(
            trim($method),
        );
    }

    /**
     * Normalise un User-Agent.
     */
    private function normalizeUserAgent(
        string $userAgent,
    ): string {
        return trim($userAgent);
    }
}