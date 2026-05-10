<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'un LogEntry est invalide.
 */
final class InvalidLogEntryException extends DomainException
{
    public static function emptyMessage(): self
    {
        return new self(
            'Log message cannot be empty.',
        );
    }

    public static function messageTooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'Log message exceeds maximum length of %d characters.',
                $maxLength,
            ),
        );
    }

    public static function emptyDomain(): self
    {
        return new self(
            'Log domain cannot be empty.',
        );
    }
}