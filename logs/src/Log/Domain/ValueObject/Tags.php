<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

/**
 * Représente un ensemble de tags normalisés.
 *
 * Responsabilités :
 * - encapsuler des métadonnées simples
 * - garantir des tags sûrs et bornés
 * - normaliser les données externes hostiles
 * - stabiliser les données de recherche
 *
 * Invariants :
 * - clés lowercase
 * - clés triées
 * - tailles bornées
 * - données scalaires uniquement
 * - immutable
 *
 * Structure :
 * [
 *     'feature' => 'checkout',
 *     'region' => 'eu-west',
 * ]
 */
final readonly class Tags
{
    /**
     * Nombre maximal de tags.
     */
    private const MAX_TAGS = 50;

    /**
     * Longueur maximale d'une clé.
     */
    private const MAX_KEY_LENGTH = 50;

    /**
     * Longueur maximale d'une valeur.
     */
    private const MAX_VALUE_LENGTH = 100;

    /**
     * Pattern autorisé pour les clés.
     */
    private const KEY_PATTERN = '/^[a-z0-9._-]+$/';

    /**
     * Tags normalisés.
     *
     * @var array<string, string>
     */
    private array $values;

    /**
     * @param array<string, string> $values
     */
    public function __construct(
        array $values,
    ) {
        $this->values = $this->normalize(
            $values,
        );
    }

    /**
     * Crée des tags depuis une donnée externe hostile.
     *
     * Règles :
     * - aucune exception
     * - fallback tableau vide
     * - nettoyage agressif
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_array($value) === false) {
            return new self([]);
        }

        try {
            return new self($value);
        } catch (\Throwable) {
            return new self([]);
        }
    }

    /**
     * Retourne les tags normalisés.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Vérifie si un tag existe.
     */
    public function has(
        string $key,
    ): bool {
        return array_key_exists(
            strtolower(trim($key)),
            $this->values,
        );
    }

    /**
     * Retourne un tag.
     */
    public function get(
        string $key,
        ?string $default = null,
    ): ?string {
        $key = strtolower(
            trim($key),
        );

        return $this->values[$key]
            ?? $default;
    }

    /**
     * Vérifie si aucun tag n'existe.
     */
    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * Retourne le nombre de tags.
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * Compare deux ensembles de tags.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->values === $other->values;
    }

    /**
     * Représentation stable.
     */
    public function __toString(): string
    {
        return json_encode(
            $this->values,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, string>
     */
    private function normalize(
        array $values,
    ): array {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (
                count($normalized)
                >= self::MAX_TAGS
            ) {
                break;
            }

            if (is_string($key) === false) {
                continue;
            }

            $key = $this->normalizeKey($key);

            if ($key === null) {
                continue;
            }

            $value = $this->normalizeValue($value);

            if ($value === null) {
                continue;
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Normalise une clé.
     */
    private function normalizeKey(
        string $key,
    ): ?string {
        $key = strtolower(
            trim($key),
        );

        if ($key === '') {
            return null;
        }

        if (
            mb_strlen($key)
            > self::MAX_KEY_LENGTH
        ) {
            $key = mb_substr(
                $key,
                0,
                self::MAX_KEY_LENGTH,
            );
        }

        if (
            preg_match(
                self::KEY_PATTERN,
                $key,
            ) !== 1
        ) {
            return null;
        }

        return $key;
    }

    /**
     * Normalise une valeur.
     */
    private function normalizeValue(
        mixed $value,
    ): ?string {
        if (
            is_scalar($value) === false
            && $value !== null
        ) {
            return null;
        }

        $value = trim(
            (string) $value,
        );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > self::MAX_VALUE_LENGTH
        ) {
            $value = mb_substr(
                $value,
                0,
                self::MAX_VALUE_LENGTH,
            );
        }

        return $value;
    }
}