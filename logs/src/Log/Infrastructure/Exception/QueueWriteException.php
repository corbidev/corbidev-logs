<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Exception;

use RuntimeException;

/**
 * Exception levée lors des écritures
 * dans la queue disque.
 */
final class QueueWriteException extends RuntimeException
{
}