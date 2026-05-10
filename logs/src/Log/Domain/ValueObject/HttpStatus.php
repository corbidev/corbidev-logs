<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidHttpStatusException;

/**
 * Représente un code HTTP valide et borné.
 *
 * Responsabilités :
 * - garantir un status HTTP valide
 * - fournir des helpers métier simples
 * - stabiliser les données utilisées par le fingerprint
 *
 * Invariants :
 * - entier strict
 * - compris entre 100 et 599
 * - immutable
 */
final readonly class HttpStatus
{
    /**
     * Status fallback utilisé lors d'une ingestion hostile.
     */
    private const FALLBACK_STATUS = 500;

    /**
     * Longueur maximale acceptable
     * pour un entier HTTP externe.
     *
     * Permet d'éviter :
     * - overflow
     * - cast implicites
     * - payloads hostiles géants
     */
    private const MAX_EXTERNAL_LENGTH = 10;

    /**
     * Valeur HTTP normalisée.
     */
    private int $value;

    /**
     * @throws InvalidHttpStatusException
     */
    public function __construct(
        int $value,
    ) {
        $this->guardValue($value);

        $this->value = $value;
    }

    /**
     * Crée un status HTTP depuis une valeur externe hostile.
     *
     * Règles :
     * - validation stricte
     * - aucun cast implicite dangereux
     * - fallback sécurisé
     * - aucune exception
     *
     * Cette méthode est destinée à l'ingestion.
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (
            is_int($value) === false
            && is_string($value) === false
        ) {
            return new self(
                self::FALLBACK_STATUS,
            );
        }

        if (
            is_string($value)
            && mb_strlen($value) > self::MAX_EXTERNAL_LENGTH
        ) {
            return new self(
                self::FALLBACK_STATUS,
            );
        }

        $status = filter_var(
            $value,
            FILTER_VALIDATE_INT,
        );

        if ($status === false) {
            return new self(
                self::FALLBACK_STATUS,
            );
        }

        if ($status < 100 || $status > 599) {
            return new self(
                self::FALLBACK_STATUS,
            );
        }

        return new self($status);
    }

    /**
     * Retourne la valeur brute.
     */
    public function value(): int
    {
        return $this->value;
    }

    /**
     * Vérifie si le status est informatif.
     */
    public function isInformational(): bool
    {
        return $this->value >= 100
            && $this->value < 200;
    }

    /**
     * Vérifie si le status est un succès.
     */
    public function isSuccess(): bool
    {
        return $this->value >= 200
            && $this->value < 300;
    }

    /**
     * Vérifie si le status est une redirection.
     */
    public function isRedirection(): bool
    {
        return $this->value >= 300
            && $this->value < 400;
    }

    /**
     * Vérifie si le status est une erreur client.
     */
    public function isClientError(): bool
    {
        return $this->value >= 400
            && $this->value < 500;
    }

    /**
     * Vérifie si le status est une erreur serveur.
     */
    public function isServerError(): bool
    {
        return $this->value >= 500
            && $this->value < 600;
    }

    /**
     * Vérifie si le status représente une erreur.
     */
    public function isError(): bool
    {
        return $this->isClientError()
            || $this->isServerError();
    }

    /**
     * Retourne la famille HTTP.
     *
     * Exemples :
     * - 2xx
     * - 4xx
     * - 5xx
     */
    public function family(): string
    {
        return (string) floor(
            $this->value / 100,
        ) . 'xx';
    }

    /**
     * Compare deux status HTTP.
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
        return (string) $this->value;
    }

    /**
     * @throws InvalidHttpStatusException
     */
    private function guardValue(
        int $value,
    ): void {
        if ($value < 100 || $value > 599) {
            throw InvalidHttpStatusException::invalidRange(
                $value,
            );
        }
    }
}