<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueWriteException;
use Throwable;

/**
 * Écrit des logs JSON dans la queue disque.
 *
 * Responsabilités :
 * - 1 log = 1 fichier
 * - écriture atomique
 * - robustesse filesystem
 * - isolation des fichiers partiels
 * - aucune perte silencieuse
 *
 * Garanties :
 * - aucun fichier partiellement visible
 * - aucun JSON tronqué
 * - écriture filesystem safe
 * - nettoyage des fichiers temporaires
 *
 * Important :
 * - le payload DOIT déjà être du JSON valide
 * - aucune sérialisation ici
 * - aucune logique métier ici
 */
final class FileQueueWriter
{
    /**
     * Extension des fichiers temporaires.
     */
    private const string TEMP_EXTENSION = '.tmp';

    public function __construct(
        private readonly QueueDirectoryManager $directoryManager,
        private readonly QueueFilenameGenerator $filenameGenerator,
    ) {
    }

    /**
     * Écrit un payload JSON dans la queue disque.
     *
     * Retourne le chemin final du fichier écrit.
     *
     * @throws QueueWriteException
     */
    public function write(
        string $directory,
        string $jsonPayload,
    ): string {
        $this->assertValidPayload($jsonPayload);

        $this->directoryManager->ensureDirectoryExists($directory);

        $filename = $this->filenameGenerator->generate();

        $finalPath = $this->buildFinalPath(
            $directory,
            $filename,
        );

        $temporaryPath = $finalPath . self::TEMP_EXTENSION;

        try {
            $this->writeTemporaryFile(
                $temporaryPath,
                $jsonPayload,
            );

            $this->moveTemporaryFile(
                $temporaryPath,
                $finalPath,
            );

            clearstatcache(true, $finalPath);

            $this->assertFinalFileExists($finalPath);

            return $finalPath;
        } catch (Throwable $exception) {
            $this->cleanupTemporaryFile($temporaryPath);

            if ($exception instanceof QueueWriteException) {
                throw $exception;
            }

            throw new QueueWriteException(
                sprintf(
                    'Erreur lors de l’écriture du fichier queue "%s" : %s',
                    $finalPath,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Écrit le fichier temporaire.
     *
     * @throws QueueWriteException
     */
    private function writeTemporaryFile(
        string $temporaryPath,
        string $payload,
    ): void {
        $handle = @fopen($temporaryPath, 'wb');

        if ($handle === false) {
            throw new QueueWriteException(
                sprintf(
                    'Impossible d’ouvrir le fichier temporaire "%s".',
                    $temporaryPath,
                ),
            );
        }

        try {
            $bytesWritten = @fwrite($handle, $payload);

            if ($bytesWritten === false) {
                throw new QueueWriteException(
                    sprintf(
                        'Impossible d’écrire dans le fichier temporaire "%s".',
                        $temporaryPath,
                    ),
                );
            }

            if ($bytesWritten !== strlen($payload)) {
                throw new QueueWriteException(
                    sprintf(
                        'Écriture partielle détectée dans "%s".',
                        $temporaryPath,
                    ),
                );
            }

            if (!@fflush($handle)) {
                throw new QueueWriteException(
                    sprintf(
                        'Impossible de flush le fichier temporaire "%s".',
                        $temporaryPath,
                    ),
                );
            }
        } finally {
            @fclose($handle);
        }
    }

    /**
     * Déplace atomiquement le fichier temporaire.
     *
     * @throws QueueWriteException
     */
    private function moveTemporaryFile(
        string $temporaryPath,
        string $finalPath,
    ): void {
        clearstatcache(true, $temporaryPath);

        if (!file_exists($temporaryPath)) {
            throw new QueueWriteException(
                sprintf(
                    'Le fichier temporaire "%s" est introuvable.',
                    $temporaryPath,
                ),
            );
        }

        if (!@rename($temporaryPath, $finalPath)) {
            throw new QueueWriteException(
                sprintf(
                    'Impossible de déplacer "%s" vers "%s".',
                    $temporaryPath,
                    $finalPath,
                ),
            );
        }
    }

    /**
     * Vérifie que le fichier final existe réellement.
     *
     * @throws QueueWriteException
     */
    private function assertFinalFileExists(string $finalPath): void
    {
        if (!file_exists($finalPath)) {
            throw new QueueWriteException(
                sprintf(
                    'Le fichier final "%s" est introuvable.',
                    $finalPath,
                ),
            );
        }

        if (!is_file($finalPath)) {
            throw new QueueWriteException(
                sprintf(
                    'Le chemin final "%s" n’est pas un fichier.',
                    $finalPath,
                ),
            );
        }

        if (!is_readable($finalPath)) {
            throw new QueueWriteException(
                sprintf(
                    'Le fichier final "%s" n’est pas lisible.',
                    $finalPath,
                ),
            );
        }
    }

    /**
     * Nettoie un fichier temporaire si présent.
     */
    private function cleanupTemporaryFile(string $temporaryPath): void
    {
        clearstatcache(true, $temporaryPath);

        if (file_exists($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }

    /**
     * Construit le chemin final du fichier.
     */
    private function buildFinalPath(
        string $directory,
        string $filename,
    ): string {
        return rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $filename;
    }

    /**
     * Vérifie qu’un payload JSON est acceptable.
     *
     * @throws QueueWriteException
     */
    private function assertValidPayload(string $payload): void
    {
        if ($payload === '') {
            throw new QueueWriteException(
                'Le payload JSON est vide.',
            );
        }

        if (!mb_check_encoding($payload, 'UTF-8')) {
            throw new QueueWriteException(
                'Le payload JSON contient un UTF-8 invalide.',
            );
        }

        json_decode($payload);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new QueueWriteException(
                sprintf(
                    'Le payload JSON est invalide : %s',
                    json_last_error_msg(),
                ),
            );
        }
    }
}