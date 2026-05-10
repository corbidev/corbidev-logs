<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidIpAddressException;

/**
 * Représente une adresse IP valide et normalisée.
 *
 * Responsabilités :
 * - garantir une IP exploitable
 * - supporter IPv4 et IPv6
 * - normaliser les données externes hostiles
 * - stabiliser les données stockées
 *
 * Invariants :
 * - IP toujours valide
 * - string immutable
 * - longueur bornée
 */
final readonly class IpAddress
{
    /**
     * IP fallback utilisée lorsque la donnée externe est invalide.
     */
    private const FALLBACK_IP = '0.0.0.0';

    /**
     * Valeur IP normalisée.
     */
    private string $value;

    /**
     * @throws InvalidIpAddressException
     */
    public function __construct(
        string $value,
    ) {
        $normalized = $this->normalize($value);

        $this->guard($normalized);

        $this->value = $normalized;
    }

    /**
     * Crée une IP depuis une donnée externe hostile.
     *
     * Règles :
     * - trim automatique
     * - fallback sécurisé
     * - aucune exception
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return new self(self::FALLBACK_IP);
        }

        try {
            return new self($value);
        } catch (InvalidIpAddressException) {
            return new self(self::FALLBACK_IP);
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
     * Vérifie si l'adresse est IPv4.
     */
    public function isV4(): bool
    {
        return filter_var(
            $this->value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4,
        ) !== false;
    }

    /**
     * Vérifie si l'adresse est IPv6.
     */
    public function isV6(): bool
    {
        return filter_var(
            $this->value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6,
        ) !== false;
    }

    /**
     * Vérifie si l'adresse est locale.
     */
    public function isLocal(): bool
    {
        return in_array(
            $this->value,
            [
                '127.0.0.1',
                '::1',
            ],
            true,
        );
    }

    /**
     * Vérifie si l'adresse est privée.
     */
    public function isPrivate(): bool
    {
        return filter_var(
            $this->value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE,
        ) === false;
    }

    /**
     * Vérifie si l'adresse est publique.
     */
    public function isPublic(): bool
    {
        return filter_var(
            $this->value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Compare deux IP.
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
     * @throws InvalidIpAddressException
     */
    private function guard(
        string $value,
    ): void {
        if (
            filter_var(
                $value,
                FILTER_VALIDATE_IP,
            ) === false
        ) {
            throw InvalidIpAddressException::invalid(
                $value,
            );
        }
    }

    /**
     * Normalise une IP externe.
     */
    private function normalize(
        string $value,
    ): string {
        return trim(
            strtolower($value),
        );
    }
}