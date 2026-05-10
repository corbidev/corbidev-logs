<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'une Request
 * est invalide.
 *
 * Responsabilités :
 * - centraliser les erreurs métier
 * - fournir des messages explicites
 * - stabiliser les erreurs domaine
 *
 * Cette exception appartient strictement
 * au domaine métier.
 */
final class InvalidRequestException extends DomainException
{
    /**
     * URI invalide.
     */
    public static function invalidUri(): self
    {
        return new self(
            'Request URI is invalid.',
        );
    }

    /**
     * Méthode HTTP invalide.
     */
    public static function invalidMethod(): self
    {
        return new self(
            'Request method is invalid.',
        );
    }

    /**
     * Méthode HTTP vide.
     */
    public static function emptyMethod(): self
    {
        return new self(
            'Request method cannot be empty.',
        );
    }

    /**
     * Méthode HTTP trop longue.
     */
    public static function methodTooLong(
        int $max,
    ): self {
        return new self(
            sprintf(
                'Request method cannot exceed %d characters.',
                $max,
            ),
        );
    }

    /**
     * User-Agent trop long.
     */
    public static function userAgentTooLong(
        int $max,
    ): self {
        return new self(
            sprintf(
                'User-Agent cannot exceed %d characters.',
                $max,
            ),
        );
    }
}