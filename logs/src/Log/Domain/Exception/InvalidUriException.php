<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception levée lorsqu'une URI est invalide.
 */
final class InvalidUriException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self(
            'URI cannot be empty.',
        );
    }

    public static function tooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'URI exceeds maximum length of %d characters.',
                $maxLength,
            ),
        );
    }

    public static function mustStartWithSlash(): self
    {
        return new self(
            'URI must start with "/".',
        );
    }
}