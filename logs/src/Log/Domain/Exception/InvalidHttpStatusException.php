<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception levée lorsqu'un code HTTP est invalide.
 */
final class InvalidHttpStatusException extends InvalidArgumentException
{
    public static function invalidRange(
        int $value,
    ): self {
        return new self(
            sprintf(
                'Invalid HTTP status "%d". Expected value between 100 and 599.',
                $value,
            ),
        );
    }
}