<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'un RequestId
 * est invalide.
 *
 * OBJECTIFS :
 * -----------
 * - messages explicites
 * - erreurs prédictibles
 * - robustesse production
 * - debugging simplifié
 */
final class InvalidRequestIdException extends DomainException
{
    /**
     * RequestId vide.
     */
    public static function empty(): self
    {
        return new self(
            'RequestId cannot be empty.',
        );
    }

    /**
     * RequestId trop court.
     */
    public static function tooShort(
        int $minLength,
    ): self {
        return new self(
            sprintf(
                'RequestId must contain at least %d characters.',
                $minLength,
            ),
        );
    }

    /**
     * RequestId trop long.
     */
    public static function tooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'RequestId cannot exceed %d characters.',
                $maxLength,
            ),
        );
    }

    /**
     * Format invalide.
     */
    public static function invalidFormat(): self
    {
        return new self(
            'RequestId contains invalid characters.',
        );
    }
}