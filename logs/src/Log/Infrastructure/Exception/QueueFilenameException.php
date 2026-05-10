<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Exception;

use RuntimeException;

/**
 * Exception levée lors de la génération
 * d’un nom de fichier de queue.
 */
final class QueueFilenameException extends RuntimeException
{
}