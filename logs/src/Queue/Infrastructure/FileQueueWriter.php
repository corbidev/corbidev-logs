<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Queue\Application\QueueWriterInterface;

/**
 * Writer disque de la queue.
 *
 * Responsabilités :
 * - sérialiser les payloads JSON
 * - écrire les fichiers queue
 * - garantir l'atomicité disque
 * - sécuriser les logs avant persistence DB
 * - nettoyer les fichiers temporaires
 *
 * Invariants :
 * - 1 log = 1 fichier
 * - écriture atomique obligatoire
 * - aucun fichier partiel
 * - aucune logique métier
 * - aucune dépendance DB
 *
 * Flux d'écriture :
 * 1. validation taille payload
 * 2. création dossiers
 * 3. génération nom fichier
 * 4. création fichier temporaire
 * 5. écriture JSON
 * 6. flush disque
 * 7. rename atomique
 * 8. chmod final
 */
final readonly class FileQueueWriter implements QueueWriterInterface
{
    public function __construct(
        private QueueConfiguration $configuration,
        private QueueFileNamingStrategy $namingStrategy,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function write(array $payload): string
    {
        $this->ensureDirectoriesExist();

        $json = $this->encodePayload($payload);

        $this->validatePayloadSize($json);

        $temporaryFile = $this->createTemporaryFile();

        $finalFile = $this->generateFinalFilePath();

        try {
            $this->writeTemporaryFile(
                $temporaryFile,
                $json,
            );

            $this->moveToFinalDestination(
                $temporaryFile,
                $finalFile,
            );

            $this->applyFilePermissions($finalFile);

            return $finalFile;
        } catch (\Throwable $exception) {
            $this->cleanupTemporaryFile($temporaryFile);

            throw new \RuntimeException(
                sprintf(
                    'Unable to write queue file "%s".',
                    $finalFile,
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Crée tous les répertoires nécessaires.
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->configuration->getQueueDirectory(),
            $this->configuration->getProcessingDirectory(),
            $this->configuration->getCorruptedDirectory(),
            $this->configuration->getFailedDirectory(),
            $this->configuration->getTemporaryDirectory(),
        ];

        foreach ($directories as $directory) {
            $this->ensureDirectoryExists($directory);
        }
    }

    /**
     * Vérifie qu'un répertoire existe.
     */
    private function ensureDirectoryExists(
        string $directory,
    ): void {
        if (is_dir($directory)) {
            return;
        }

        $created = @mkdir(
            $directory,
            $this->configuration->getDirectoryPermissions(),
            true,
        );

        if (
            false === $created
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to create queue directory "%s".',
                    $directory,
                ),
            );
        }
    }

    /**
     * Génère le chemin final du fichier queue.
     */
    private function generateFinalFilePath(): string
    {
        return sprintf(
            '%s/%s',
            rtrim(
                $this->configuration->getQueueDirectory(),
                '/',
            ),
            $this->namingStrategy->generate(),
        );
    }

    /**
     * Sérialise un payload JSON sécurisé.
     *
     * @param array<string, mixed> $payload
     *
     * @throws \JsonException
     */
    private function encodePayload(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    /**
     * Vérifie la taille maximale du payload.
     */
    private function validatePayloadSize(
        string $json,
    ): void {
        $size = strlen($json);

        if (
            $size
            > $this->configuration->getMaxPayloadSize()
        ) {
            throw new \RuntimeException(
                sprintf(
                    'Queue payload exceeds maximum allowed size (%d bytes).',
                    $this->configuration->getMaxPayloadSize(),
                ),
            );
        }
    }

    /**
     * Crée un fichier temporaire sécurisé.
     */
    private function createTemporaryFile(): string
    {
        $temporaryFile = @tempnam(
            $this->configuration->getTemporaryDirectory(),
            'queue_',
        );

        if (false === $temporaryFile) {
            throw new \RuntimeException(
                'Unable to create temporary queue file.',
            );
        }

        return $temporaryFile;
    }

    /**
     * Écrit le contenu JSON dans le fichier temporaire.
     */
    private function writeTemporaryFile(
        string $temporaryFile,
        string $content,
    ): void {
        $handle = @fopen(
            $temporaryFile,
            'wb',
        );

        if (false === $handle) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to open temporary queue file "%s".',
                    $temporaryFile,
                ),
            );
        }

        try {
            $this->lockFile($handle, $temporaryFile);

            $writtenBytes = @fwrite(
                $handle,
                $content,
            );

            if (false === $writtenBytes) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to write temporary queue file "%s".',
                        $temporaryFile,
                    ),
                );
            }

            if ($writtenBytes !== strlen($content)) {
                throw new \RuntimeException(
                    sprintf(
                        'Partial write detected for temporary queue file "%s".',
                        $temporaryFile,
                    ),
                );
            }

            if (!@fflush($handle)) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to flush temporary queue file "%s".',
                        $temporaryFile,
                    ),
                );
            }
        } finally {
            @flock($handle, LOCK_UN);

            fclose($handle);
        }
    }

    /**
     * Verrouille le fichier temporaire.
     *
     * @param resource $handle
     */
    private function lockFile(
        mixed $handle,
        string $temporaryFile,
    ): void {
        if (!@flock($handle, LOCK_EX)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to lock temporary queue file "%s".',
                    $temporaryFile,
                ),
            );
        }
    }

    /**
     * Déplace atomiquement le fichier temporaire
     * vers sa destination finale.
     */
    private function moveToFinalDestination(
        string $temporaryFile,
        string $finalFile,
    ): void {
        if (file_exists($finalFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Queue file collision detected for "%s".',
                    $finalFile,
                ),
            );
        }

        if (!@rename($temporaryFile, $finalFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to move queue file to "%s".',
                    $finalFile,
                ),
            );
        }
    }

    /**
     * Applique les permissions UNIX du fichier final.
     */
    private function applyFilePermissions(
        string $file,
    ): void {
        $applied = @chmod(
            $file,
            $this->configuration->getFilePermissions(),
        );

        if (false === $applied) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to apply permissions on queue file "%s".',
                    $file,
                ),
            );
        }
    }

    /**
     * Nettoie un fichier temporaire.
     */
    private function cleanupTemporaryFile(
        string $temporaryFile,
    ): void {
        if (
            file_exists($temporaryFile)
            && is_file($temporaryFile)
        ) {
            @unlink($temporaryFile);
        }
    }
}