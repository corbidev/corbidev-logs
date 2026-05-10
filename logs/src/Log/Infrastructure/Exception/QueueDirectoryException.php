<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Exception;

use RuntimeException;

/**
 * Exception levée lors des opérations
 * liées aux dossiers de queue.
 */
final class QueueDirectoryException extends RuntimeException
{
}