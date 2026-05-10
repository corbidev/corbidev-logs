<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception levée lorsqu'un client est invalide.
 */
final class InvalidClientException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self(
            'Client cannot be empty.',
        );
    }

    public static function tooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'Client exceeds maximum length of %d characters.',
                $maxLength,
            ),
        );
    }

    public static function invalidFormat(): self
    {
        return new self(
            'Client contains invalid characters.',
        );
    }
}