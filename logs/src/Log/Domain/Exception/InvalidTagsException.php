<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'un ensemble
 * de tags est invalide.
 *
 * Responsabilités :
 * - centraliser les erreurs métier
 * - fournir des messages explicites
 * - stabiliser les erreurs domaine
 *
 * Cette exception appartient strictement
 * au domaine métier.
 */
final class InvalidTagsException extends DomainException
{
    /**
     * Nombre maximal de tags dépassé.
     */
    public static function tooManyTags(
        int $max,
    ): self {
        return new self(
            sprintf(
                'Tags cannot contain more than %d entries.',
                $max,
            ),
        );
    }

    /**
     * Clé de tag invalide.
     */
    public static function invalidKey(
        string $key,
    ): self {
        return new self(
            sprintf(
                'Invalid tag key "%s".',
                $key,
            ),
        );
    }

    /**
     * Valeur de tag invalide.
     */
    public static function invalidValue(
        string $key,
    ): self {
        return new self(
            sprintf(
                'Invalid value for tag "%s".',
                $key,
            ),
        );
    }

    /**
     * Clé de tag vide.
     */
    public static function emptyKey(): self
    {
        return new self(
            'Tag key cannot be empty.',
        );
    }

    /**
     * Longueur de clé invalide.
     */
    public static function keyTooLong(
        int $max,
    ): self {
        return new self(
            sprintf(
                'Tag key cannot exceed %d characters.',
                $max,
            ),
        );
    }

    /**
     * Longueur de valeur invalide.
     */
    public static function valueTooLong(
        int $max,
    ): self {
        return new self(
            sprintf(
                'Tag value cannot exceed %d characters.',
                $max,
            ),
        );
    }
}