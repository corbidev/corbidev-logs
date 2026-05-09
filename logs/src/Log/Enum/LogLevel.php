<?php

declare(strict_types=1);

namespace App\Log\Enum;

/**
 * Représente les niveaux PSR-3 autorisés.
 *
 * Cet enum garantit :
 * - des niveaux valides,
 * - une validation type-safe,
 * - une centralisation des niveaux supportés.
 */
enum LogLevel: string
{
    case DEBUG = 'debug';

    case INFO = 'info';

    case NOTICE = 'notice';

    case WARNING = 'warning';

    case ERROR = 'error';

    case CRITICAL = 'critical';

    case ALERT = 'alert';

    case EMERGENCY = 'emergency';

    /**
     * Retourne le niveau fallback.
     */
    public static function default(): self
    {
        return self::ERROR;
    }
}