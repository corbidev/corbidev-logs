<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidFingerprintException;

/**
 * Représente un fingerprint stable et borné.
 *
 * Responsabilités :
 * - identifier un groupe logique de logs
 * - stabiliser les regroupements
 * - garantir un format court et prédictible
 * - sécuriser les données utilisées en recherche
 *
 * Invariants :
 * - jamais vide
 * - lowercase uniquement
 * - format hexadécimal
 * - longueur fixe
 * - immutable
 *
 * Format attendu :
 * - sha1 tronqué
 * - 16 caractères
 * - [a-f0-9]
 */
final readonly class Fingerprint
{
    /**
     * Longueur exacte attendue.
     */
    private const LENGTH = 16;

    /**
     * Pattern autorisé.
     */
    private const PATTERN = '/^[a-f0-9]{16}$/';

    /**
     * Fingerprint fallback ingestion.
     */
    private const FALLBACK = '0000000000000000';

    /**
     * Valeur fingerprint normalisée.
     */
    private string $value;

    /**
     * @throws InvalidFingerprintException
     */
    public function __construct(
        string $value,
    ) {
        $normalized = $this->normalize($value);

        $this->guard($normalized);

        $this->value = $normalized;
    }

    /**
     * Crée un fingerprint depuis une donnée externe hostile.
     *
     * Règles :
     * - lowercase automatique
     * - trim automatique
     * - fallback sécurisé
     * - aucune exception
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return new self(
                self::FALLBACK,
            );
        }

        try {
            return new self($value);
        } catch (InvalidFingerprintException) {
            return new self(
                self::FALLBACK,
            );
        }
    }

    /**
     * Génère un fingerprint depuis une base métier.
     *
     * Règles :
     * - trim
     * - lowercase
     * - sha1
     * - truncation 16 chars
     */
    public static function generate(
        string $base,
    ): self {
        $base = strtolower(
            trim($base),
        );

        return new self(
            substr(
                sha1($base),
                0,
                self::LENGTH,
            ),
        );
    }

    /**
     * Retourne la valeur normalisée.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vérifie si le fingerprint
     * correspond au fallback ingestion.
     */
    public function isFallback(): bool
    {
        return $this->value === self::FALLBACK;
    }

    /**
     * Compare deux fingerprints.
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
     * @throws InvalidFingerprintException
     */
    private function guard(
        string $value,
    ): void {
        if ($value === '') {
            throw InvalidFingerprintException::empty();
        }

        if (
            mb_strlen($value)
            !== self::LENGTH
        ) {
            throw InvalidFingerprintException::invalidLength(
                self::LENGTH,
            );
        }

        if (
            preg_match(
                self::PATTERN,
                $value,
            ) !== 1
        ) {
            throw InvalidFingerprintException::invalidFormat();
        }
    }

    /**
     * Normalise une valeur externe.
     */
    private function normalize(
        string $value,
    ): string {
        return strtolower(
            trim($value),
        );
    }
}