<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'un fingerprint est invalide.
 *
 * Responsabilités :
 * - fournir des erreurs explicites
 * - centraliser les messages métier
 * - stabiliser les erreurs domaine
 *
 * Cette exception appartient strictement au domaine.
 */
final class InvalidFingerprintException extends DomainException
{
    /**
     * Fingerprint vide.
     */
    public static function empty(): self
    {
        return new self(
            'Fingerprint cannot be empty.',
        );
    }

    /**
     * Longueur invalide.
     */
    public static function invalidLength(
        int $expected,
    ): self {
        return new self(
            sprintf(
                'Fingerprint must contain exactly %d characters.',
                $expected,
            ),
        );
    }

    /**
     * Format invalide.
     */
    public static function invalidFormat(): self
    {
        return new self(
            'Fingerprint must contain only lowercase hexadecimal characters.',
        );
    }
}