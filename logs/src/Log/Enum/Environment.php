<?php

declare(strict_types=1);

namespace App\Log\Enum;

/**
 * Représente les environnements applicatifs supportés.
 *
 * Responsabilités :
 * - fournir une liste bornée d'environnements
 * - normaliser les valeurs externes hostiles
 * - garantir des valeurs cohérentes pour le domaine
 * - stabiliser les données utilisées pour le fingerprint
 *
 * Invariants :
 * - aucune valeur invalide ne sort du domaine
 * - toutes les valeurs sont lowercase
 * - toutes les valeurs sont courtes et prédictibles
 */
enum Environment: string
{
    /**
     * Environnement de production.
     */
    case Production = 'prod';

    /**
     * Environnement de préproduction / staging.
     */
    case Staging = 'staging';

    /**
     * Environnement de développement.
     */
    case Development = 'dev';

    /**
     * Environnement de test automatisé.
     */
    case Test = 'test';

    /**
     * Environnement fallback utilisé
     * lors d'une ingestion hostile.
     */
    private const FALLBACK = self::Production;

    /**
     * Crée un environnement depuis une valeur externe hostile.
     *
     * Règles :
     * - trim automatique
     * - lowercase automatique
     * - aliases supportés
     * - fallback explicite vers production
     * - aucune exception
     *
     * Garanties :
     * - robustesse ingestion
     * - pipeline prédictible
     * - absence de crash
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return self::FALLBACK;
        }

        $normalized = self::normalize($value);

        return match ($normalized) {
            'prod',
            'production',
            'live' => self::Production,

            'staging',
            'stage',
            'preprod',
            'pre-production',
            'preproduction' => self::Staging,

            'dev',
            'development',
            'local',
            'localhost' => self::Development,

            'test',
            'testing',
            'ci',
            'tests' => self::Test,

            default => self::FALLBACK,
        };
    }

    /**
     * Vérifie si l'environnement est la production.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Vérifie si l'environnement est un environnement de développement.
     */
    public function isDevelopment(): bool
    {
        return $this === self::Development;
    }

    /**
     * Vérifie si l'environnement est un environnement de staging.
     */
    public function isStaging(): bool
    {
        return $this === self::Staging;
    }

    /**
     * Vérifie si l'environnement est un environnement de test.
     */
    public function isTest(): bool
    {
        return $this === self::Test;
    }

    /**
     * Retourne la liste des valeurs supportées.
     *
     * Utile pour :
     * - documentation API
     * - validation frontend
     * - debug
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (
                self $environment,
            ): string => $environment->value,
            self::cases(),
        );
    }

    /**
     * Tente de résoudre un environnement depuis une valeur externe.
     *
     * Retourne null si la valeur est invalide ou inconnue.
     * Ne lève jamais d'exception.
     */
    public static function tryFromExternal(
        mixed $value,
    ): ?self {
        if (is_string($value) === false) {
            return null;
        }

        $normalized = self::normalize($value);

        return match ($normalized) {
            'prod',
            'production',
            'live' => self::Production,

            'staging',
            'stage',
            'preprod',
            'pre-production',
            'preproduction' => self::Staging,

            'dev',
            'development',
            'local',
            'localhost' => self::Development,

            'test',
            'testing',
            'ci',
            'tests' => self::Test,

            default => null,
        };
    }

    /**
     * Normalise une valeur externe.
     *
     * Règles :
     * - trim
     * - lowercase
     * - suppression espaces parasites
     */
    private static function normalize(
        string $value,
    ): string {
        return strtolower(
            trim($value),
        );
    }
}
