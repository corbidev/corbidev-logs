<?php

declare(strict_types=1);

namespace App\Log\Enum;

/**
 * Représente les niveaux PSR-3 autorisés.
 *
 * Responsabilités :
 * - garantir des niveaux valides
 * - fournir des helpers métier
 * - normaliser les données externes hostiles
 * - stabiliser les niveaux utilisés par le domaine
 *
 * Invariants :
 * - valeurs bornées
 * - lowercase
 * - immutable
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
     * Niveau fallback ingestion.
     */
    public static function default(): self
    {
        return self::ERROR;
    }

    /**
     * Crée un niveau depuis une donnée externe hostile.
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return self::default();
        }

        $normalized = strtolower(
            trim($value),
        );

        return self::tryFrom($normalized)
            ?? self::default();
    }

    /**
     * Vérifie si le niveau représente une erreur.
     */
    public function isError(): bool
    {
        return match ($this) {
            self::ERROR,
            self::CRITICAL,
            self::ALERT,
            self::EMERGENCY => true,

            default => false,
        };
    }

    /**
     * Vérifie si le niveau est critique.
     */
    public function isCritical(): bool
    {
        return match ($this) {
            self::CRITICAL,
            self::ALERT,
            self::EMERGENCY => true,

            default => false,
        };
    }
}