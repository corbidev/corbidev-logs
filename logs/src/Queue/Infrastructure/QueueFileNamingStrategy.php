<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Génère des noms de fichiers queue uniques et ordonnables.
 *
 * Responsabilités :
 * - générer des noms filesystem-safe
 * - garantir une unicité pratique
 * - fournir un ordre FIFO approximatif
 * - appliquer une extension stable
 *
 * Format généré :
 *
 * YYYYMMDD_HHMMSS_random.ext
 *
 * Exemple :
 *
 * 20260510_021522_a1b2c3d4.json
 *
 * Invariants :
 * - UTC uniquement
 * - caractères ASCII uniquement
 * - extension validée
 * - aucune dépendance IO disque
 * - aucun état mutable
 *
 * Cette classe ne doit jamais :
 * - créer des fichiers
 * - accéder au filesystem
 * - dépendre de la DB
 * - dépendre de Symfony
 */
final readonly class QueueFileNamingStrategy
{
    /**
     * Longueur du suffixe aléatoire hexadécimal.
     */
    private const RANDOM_HEX_LENGTH = 8;

    /**
     * @param non-empty-string $extension
     * Extension des fichiers queue.
     *
     * Ne doit pas contenir ".".
     */
    public function __construct(
        private string $extension = 'json',
    ) {
        $this->validateExtension();
    }

    /**
     * Génère un nom de fichier queue unique.
     *
     * Exemple :
     *
     * 20260510_021522_a1b2c3d4.json
     *
     * @throws \RuntimeException
     * Si la génération cryptographique échoue.
     */
    public function generate(): string
    {
        return sprintf(
            '%s_%s.%s',
            $this->generateTimestamp(),
            $this->generateRandomSuffix(),
            $this->extension,
        );
    }

    /**
     * Génère le timestamp UTC.
     *
     * Format :
     *
     * YYYYMMDD_HHMMSS
     */
    private function generateTimestamp(): string
    {
        return (new DateTimeImmutable(
            timezone: new DateTimeZone('UTC'),
        ))->format('Ymd_His');
    }

    /**
     * Génère un suffixe aléatoire hexadécimal.
     *
     * Exemple :
     *
     * a1b2c3d4
     *
     * @throws \RuntimeException
     * Si random_bytes échoue.
     */
    private function generateRandomSuffix(): string
    {
        try {
            return bin2hex(
                random_bytes(
                    self::RANDOM_HEX_LENGTH / 2,
                ),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                'Unable to generate queue filename random suffix.',
                previous: $exception,
            );
        }
    }

    /**
     * Vérifie la validité de l'extension.
     */
    private function validateExtension(): void
    {
        if ('' === trim($this->extension)) {
            throw new \InvalidArgumentException(
                'Queue file extension cannot be empty.',
            );
        }

        if (str_contains($this->extension, '.')) {
            throw new \InvalidArgumentException(
                'Queue file extension must not contain ".".',
            );
        }

        if (!preg_match('/^[a-z0-9]+$/', $this->extension)) {
            throw new \InvalidArgumentException(
                'Queue file extension contains invalid characters.',
            );
        }
    }
}