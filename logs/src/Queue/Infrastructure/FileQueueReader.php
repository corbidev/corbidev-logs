<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Queue\Application\QueueReaderInterface;

/**
 * Reader disque de la queue.
 *
 * Responsabilités :
 * - lire les fichiers queue par batch
 * - déplacer les fichiers en processing
 * - décoder les payloads JSON
 * - isoler les fichiers corrompus
 * - garantir une mémoire bornée
 * - supprimer les fichiers après persistence DB
 *
 * Invariants :
 * - lecture progressive uniquement
 * - aucun chargement massif mémoire
 * - aucun fichier corrompu ne bloque la queue
 * - aucune logique métier
 * - aucune dépendance Doctrine
 *
 * Flux de lecture :
 * 1. scan fichiers queue
 * 2. tri FIFO approximatif
 * 3. limitation batch
 * 4. move vers processing/
 * 5. lecture JSON
 * 6. validation minimale
 * 7. yield payload valide
 * 8. corrupted/ si erreur
 */
final readonly class FileQueueReader implements QueueReaderInterface
{
    public function __construct(
        private QueueConfiguration $configuration,
        private CorruptedQueueFileManager $corruptedFileManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function readBatch(
        int $limit = 100,
    ): iterable {
        if ($limit <= 0) {
            throw new \InvalidArgumentException(
                'Queue batch limit must be greater than zero.',
            );
        }

        $files = $this->getQueueFiles();

        $files = array_slice(
            $files,
            0,
            $limit,
        );

        foreach ($files as $file) {
            $processingFile = null;

            try {
                $processingFile = $this->markAsProcessing($file);

                $payload = $this->readPayload(
                    $processingFile,
                );

                yield [
                    'path' => $processingFile,
                    'payload' => $payload,
                ];
            } catch (\Throwable) {
                if (
                    null !== $processingFile
                    && file_exists($processingFile)
                ) {
                    $this->corruptedFileManager->move(
                        $processingFile,
                    );
                }

                continue;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(
        string $path,
    ): void {
        $this->validateFilePath($path);

        if (!file_exists($path)) {
            return;
        }

        if (!@unlink($path)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to delete queue file "%s".',
                    $path,
                ),
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markAsProcessing(
        string $path,
    ): string {
        $this->validateFilePath($path);

        if (!file_exists($path)) {
            throw new \RuntimeException(
                sprintf(
                    'Queue file "%s" does not exist.',
                    $path,
                ),
            );
        }

        $this->ensureProcessingDirectoryExists();

        $destination = sprintf(
            '%s/%s',
            rtrim(
                $this->configuration->getProcessingDirectory(),
                '/',
            ),
            basename($path),
        );

        if (!@rename($path, $destination)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to move queue file "%s" to processing.',
                    $path,
                ),
            );
        }

        return $destination;
    }

    /**
     * {@inheritDoc}
     */
    public function moveToFailed(
        string $path,
    ): string {
        $this->validateFilePath($path);

        if (!file_exists($path)) {
            throw new \RuntimeException(
                sprintf(
                    'Queue file "%s" does not exist.',
                    $path,
                ),
            );
        }

        $this->ensureFailedDirectoryExists();

        $destination = sprintf(
            '%s/%s',
            rtrim(
                $this->configuration->getFailedDirectory(),
                '/',
            ),
            basename($path),
        );

        if (!@rename($path, $destination)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to move queue file "%s" to failed.',
                    $path,
                ),
            );
        }

        return $destination;
    }

    /**
     * Retourne les fichiers queue triés.
     *
     * @return list<string>
     */
    private function getQueueFiles(): array
    {
        $pattern = sprintf(
            '%s/*.%s',
            rtrim(
                $this->configuration->getQueueDirectory(),
                '/',
            ),
            $this->configuration->getFileExtension(),
        );

        $files = glob($pattern);

        if (false === $files) {
            throw new \RuntimeException(
                'Unable to scan queue directory.',
            );
        }

        sort(
            $files,
            SORT_STRING,
        );

        return array_values(
            array_filter(
                $files,
                static fn (mixed $file): bool => is_string($file)
                    && is_file($file),
            ),
        );
    }

    /**
     * Lit et décode un payload queue.
     *
     * @return array<string, mixed>
     */
    private function readPayload(
        string $file,
    ): array {
        $content = @file_get_contents($file);

        if (false === $content) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to read queue file "%s".',
                    $file,
                ),
            );
        }

        if ('' === trim($content)) {
            throw new \RuntimeException(
                sprintf(
                    'Queue file "%s" is empty.',
                    $file,
                ),
            );
        }

        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                sprintf(
                    'Queue file "%s" does not contain a valid payload.',
                    $file,
                ),
            );
        }

        return $decoded;
    }

    /**
     * Vérifie le chemin du fichier.
     */
    private function validateFilePath(
        string $path,
    ): void {
        if ('' === trim($path)) {
            throw new \InvalidArgumentException(
                'Queue file path cannot be empty.',
            );
        }
    }

    /**
     * Vérifie l'existence du dossier processing/.
     */
    private function ensureProcessingDirectoryExists(): void
    {
        $directory = $this->configuration->getProcessingDirectory();

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
                    'Unable to create processing directory "%s".',
                    $directory,
                ),
            );
        }
    }

    /**
     * Vérifie l'existence du dossier failed/.
     */
    private function ensureFailedDirectoryExists(): void
    {
        $directory = $this->configuration->getFailedDirectory();

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
                    'Unable to create failed directory "%s".',
                    $directory,
                ),
            );
        }
    }
}