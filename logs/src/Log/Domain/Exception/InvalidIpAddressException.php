<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception levée lorsqu'une adresse IP est invalide.
 */
final class InvalidIpAddressException extends InvalidArgumentException
{
    public static function invalid(
        string $value,
    ): self {
        return new self(
            sprintf(
                'Invalid IP address "%s".',
                $value,
            ),
        );
    }
}