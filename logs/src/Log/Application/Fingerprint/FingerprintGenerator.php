<?php

declare(strict_types=1);

namespace App\Log\Application\Fingerprint;

use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;

/**
 * Génère des fingerprints stables et prédictibles.
 *
 * Responsabilités :
 * - stabiliser le regroupement des erreurs
 * - produire des signatures déterministes
 * - ignorer les données volatiles
 * - protéger contre les variations externes
 *
 * Format :
 * level|httpStatus|domain|uri|env
 *
 * Règles :
 * - lowercase
 * - trim
 * - query string ignorée
 * - hash sha1 tronqué
 * - longueur stable
 */
final readonly class FingerprintGenerator
{
    /**
     * Longueur finale du fingerprint.
     */
    private const LENGTH = 16;

    /**
     * Génère un fingerprint stable.
     */
    public function generate(
        LogLevel $level,
        HttpStatus $httpStatus,
        string $domain,
        Uri $uri,
        Environment $environment,
    ): Fingerprint {
        $payload = implode(
            '|',
            [
                $this->normalize(
                    $level->value,
                ),

                (string) $httpStatus->value(),

                $this->normalize(
                    $domain,
                ),

                $this->normalize(
                    $uri->value(),
                ),

                $this->normalize(
                    $environment->value,
                ),
            ],
        );

        return new Fingerprint(
            substr(
                sha1($payload),
                0,
                self::LENGTH,
            ),
        );
    }

    /**
     * Normalise une valeur utilisée dans le fingerprint.
     */
    private function normalize(
        string $value,
    ): string {
        return strtolower(
            trim($value),
        );
    }
}