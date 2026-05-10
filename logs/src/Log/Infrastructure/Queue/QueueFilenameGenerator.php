<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueFilenameException;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

/**
 * Génère des noms de fichiers de queue robustes,
 * uniques et triables chronologiquement.
 *
 * Format :
 * YYYYMMDD_HHMMSS_microseconds_random.json
 *
 * Exemple :
 * 20260510_013015_654321_ab12cd34ef56.json
 *
 * Garanties :
 * - tri chronologique naturel
 * - compatibilité filesystem
 * - absence de caractères dangereux
 * - collisions extrêmement improbables
 * - validation stricte du format
 * - robustesse production mutualisée
 */
final class QueueFilenameGenerator
{
    /**
     * Regex stricte du format attendu.
     */
    private const string FILENAME_PATTERN =
        '/^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Génère un nom de fichier de queue.
     *
     * @throws QueueFilenameException
     */
    public function generate(): string
    {
        try {
            $date = $this->clock->now();

            $timestamp = $date->format('Ymd_His');

            $microseconds = $date->format('u');

            $random = $this->generateRandomSegment();

            $filename = sprintf(
                '%s_%s_%s.json',
                $timestamp,
                $microseconds,
                $random,
            );

            $this->assertValidFilename($filename);

            return $filename;
        } catch (QueueFilenameException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new QueueFilenameException(
                sprintf(
                    'Impossible de générer un nom de fichier de queue : %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Génère un segment aléatoire sécurisé.
     *
     * @throws QueueFilenameException
     */
    private function generateRandomSegment(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (Throwable $exception) {
            throw new QueueFilenameException(
                sprintf(
                    'Impossible de générer une entropie sécurisée : %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Vérifie que le filename généré respecte
     * strictement le format attendu.
     *
     * @throws QueueFilenameException
     */
    private function assertValidFilename(string $filename): void
    {
        if ($filename === '') {
            throw new QueueFilenameException(
                'Le nom de fichier généré est vide.',
            );
        }

        if (mb_strlen($filename) > 255) {
            throw new QueueFilenameException(
                'Le nom de fichier dépasse la limite filesystem.',
            );
        }

        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            throw new QueueFilenameException(
                sprintf(
                    'Le nom de fichier généré est invalide : "%s".',
                    $filename,
                ),
            );
        }
    }
}