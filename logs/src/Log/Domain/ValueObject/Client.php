<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidClientException;

/**
 * Représente le client applicatif émetteur du log.
 *
 * Responsabilités :
 * - identifier le client source
 * - normaliser les données externes hostiles
 * - stabiliser les données de regroupement
 * - garantir une valeur courte et prédictible
 *
 * Invariants :
 * - jamais vide
 * - lowercase
 * - longueur bornée
 * - caractères sûrs uniquement
 * - immutable
 */
final readonly class Client
{
    /**
     * Longueur maximale autorisée.
     */
    private const MAX_LENGTH = 100;

    /**
     * Client fallback utilisé pour ingestion hostile.
     */
    private const FALLBACK = 'unknown';

    /**
     * Pattern autorisé.
     *
     * Exemples valides :
     * - symfony-app
     * - api-gateway
     * - checkout_service
     * - mobile.v2
     */
    private const PATTERN = '/^[a-z0-9._-]+$/';

    /**
     * Valeur normalisée du client.
     */
    private string $value;

    /**
     * @throws InvalidClientException
     */
    public function __construct(
        string $value,
    ) {
        $normalized = $this->normalize($value);

        $this->guard($normalized);

        $this->value = $normalized;
    }

    /**
     * Crée un client depuis une donnée externe hostile.
     *
     * Règles :
     * - trim automatique
     * - lowercase automatique
     * - fallback sécurisé
     * - aucune exception
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return new self(self::FALLBACK);
        }

        try {
            return new self($value);
        } catch (InvalidClientException) {
            return new self(self::FALLBACK);
        }
    }

    /**
     * Retourne la valeur normalisée.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vérifie si le client est le fallback système.
     */
    public function isUnknown(): bool
    {
        return $this->value === self::FALLBACK;
    }

    /**
     * Compare deux clients.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->value === $other->value;
    }

    /**
     * Représentation string stable.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidClientException
     */
    private function guard(
        string $value,
    ): void {
        if ($value === '') {
            throw InvalidClientException::empty();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidClientException::tooLong(
                self::MAX_LENGTH,
            );
        }

        if (
            preg_match(
                self::PATTERN,
                $value,
            ) !== 1
        ) {
            throw InvalidClientException::invalidFormat();
        }
    }

    /**
     * Normalise une valeur externe.
     */
    private function normalize(
        string $value,
    ): string {
        $value = trim(
            strtolower($value),
        );

        return preg_replace(
            '/\s+/',
            '-',
            $value,
        ) ?? '';
    }
}