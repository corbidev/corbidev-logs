<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

/**
 * Configuration centralisée de la queue disque.
 *
 * Responsabilités :
 * - centraliser les chemins queue
 * - fournir les limites techniques
 * - sécuriser les paramètres critiques
 * - garantir une configuration cohérente
 *
 * Invariants :
 * - tous les chemins sont absolus
 * - toutes les limites sont bornées
 * - aucune valeur invalide n'est acceptée
 *
 * Cette classe ne contient :
 * - aucune logique métier
 * - aucun accès disque
 * - aucun état mutable
 */
final readonly class QueueConfiguration
{
    /**
     * @param string $baseDirectory
     * Répertoire racine de la queue.
     *
     * Exemple :
     * /var/www/app/var/queue
     *
     * @param positive-int $readBatchSize
     * Taille maximale d'un batch de lecture.
     *
     * @param positive-int $maxRetries
     * Nombre maximal de retries avant failed/.
     *
     * @param positive-int $maxPayloadSize
     * Taille maximale autorisée d'un payload JSON.
     *
     * Valeur exprimée en octets.
     *
     * @param non-empty-string $queueDirectoryName
     * Nom du dossier principal de queue.
     *
     * @param non-empty-string $processingDirectoryName
     * Nom du dossier processing.
     *
     * @param non-empty-string $corruptedDirectoryName
     * Nom du dossier corrupted.
     *
     * @param non-empty-string $failedDirectoryName
     * Nom du dossier failed.
     *
     * @param non-empty-string $temporaryDirectoryName
     * Nom du dossier temporaire.
     *
     * @param non-empty-string $fileExtension
     * Extension des fichiers queue.
     *
     * @param int $directoryPermissions
     * Permissions UNIX des dossiers.
     *
     * @param int $filePermissions
     * Permissions UNIX des fichiers.
     */
    public function __construct(
        private string $baseDirectory,
        private int $readBatchSize = 100,
        private int $maxRetries = 3,
        private int $maxPayloadSize = 1048576,
        private string $queueDirectoryName = 'logs',
        private string $processingDirectoryName = 'processing',
        private string $corruptedDirectoryName = 'corrupted',
        private string $failedDirectoryName = 'failed',
        private string $temporaryDirectoryName = 'tmp',
        private string $fileExtension = 'json',
        private int $directoryPermissions = 0775,
        private int $filePermissions = 0664,
    ) {
        $this->validate();
    }

    /**
     * Retourne le répertoire racine de la queue.
     */
    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    /**
     * Retourne le répertoire principal des logs queue.
     */
    public function getQueueDirectory(): string
    {
        return $this->buildPath($this->queueDirectoryName);
    }

    /**
     * Retourne le répertoire processing.
     */
    public function getProcessingDirectory(): string
    {
        return $this->buildPath($this->processingDirectoryName);
    }

    /**
     * Retourne le répertoire corrupted.
     */
    public function getCorruptedDirectory(): string
    {
        return $this->buildPath($this->corruptedDirectoryName);
    }

    /**
     * Retourne le répertoire failed.
     */
    public function getFailedDirectory(): string
    {
        return $this->buildPath($this->failedDirectoryName);
    }

    /**
     * Retourne le répertoire temporaire.
     */
    public function getTemporaryDirectory(): string
    {
        return $this->buildPath($this->temporaryDirectoryName);
    }

    /**
     * Retourne la taille maximale d'un batch de lecture.
     *
     * @return positive-int
     */
    public function getReadBatchSize(): int
    {
        return $this->readBatchSize;
    }

    /**
     * Retourne le nombre maximal de retries.
     *
     * @return positive-int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Retourne la taille maximale autorisée d'un payload.
     *
     * Valeur exprimée en octets.
     *
     * @return positive-int
     */
    public function getMaxPayloadSize(): int
    {
        return $this->maxPayloadSize;
    }

    /**
     * Retourne l'extension des fichiers queue.
     */
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Retourne les permissions UNIX des dossiers.
     */
    public function getDirectoryPermissions(): int
    {
        return $this->directoryPermissions;
    }

    /**
     * Retourne les permissions UNIX des fichiers.
     */
    public function getFilePermissions(): int
    {
        return $this->filePermissions;
    }

    /**
     * Construit un chemin absolu à partir du répertoire racine.
     */
    private function buildPath(string $directory): string
    {
        return sprintf(
            '%s/%s',
            rtrim($this->baseDirectory, '/'),
            trim($directory, '/'),
        );
    }

    /**
     * Valide la cohérence complète de la configuration.
     */
    private function validate(): void
    {
        $this->validateBaseDirectory();
        $this->validatePositiveIntegers();
        $this->validateDirectoryNames();
        $this->validateFileExtension();
        $this->validatePermissions();
    }

    /**
     * Vérifie le répertoire racine.
     */
    private function validateBaseDirectory(): void
    {
        if ('' === trim($this->baseDirectory)) {
            throw new \InvalidArgumentException(
                'Queue base directory cannot be empty.',
            );
        }

        if (!$this->isAbsolutePath($this->baseDirectory)) {
            throw new \InvalidArgumentException(
                'Queue base directory must be absolute.',
            );
        }
    }

    /**
     * Vérifie les limites numériques.
     */
    private function validatePositiveIntegers(): void
    {
        if ($this->readBatchSize <= 0) {
            throw new \InvalidArgumentException(
                'Read batch size must be greater than zero.',
            );
        }

        if ($this->maxRetries <= 0) {
            throw new \InvalidArgumentException(
                'Max retries must be greater than zero.',
            );
        }

        if ($this->maxPayloadSize <= 0) {
            throw new \InvalidArgumentException(
                'Max payload size must be greater than zero.',
            );
        }
    }

    /**
     * Vérifie les noms des dossiers.
     */
    private function validateDirectoryNames(): void
    {
        $directories = [
            $this->queueDirectoryName,
            $this->processingDirectoryName,
            $this->corruptedDirectoryName,
            $this->failedDirectoryName,
            $this->temporaryDirectoryName,
        ];

        foreach ($directories as $directory) {
            if ('' === trim($directory)) {
                throw new \InvalidArgumentException(
                    'Queue directory names cannot be empty.',
                );
            }

            if (str_contains($directory, '..')) {
                throw new \InvalidArgumentException(
                    'Queue directory names cannot contain "..".',
                );
            }

            if (str_contains($directory, '/')) {
                throw new \InvalidArgumentException(
                    'Queue directory names cannot contain "/".',
                );
            }
        }
    }

    /**
     * Vérifie l'extension des fichiers queue.
     */
    private function validateFileExtension(): void
    {
        if ('' === trim($this->fileExtension)) {
            throw new \InvalidArgumentException(
                'Queue file extension cannot be empty.',
            );
        }

        if (str_contains($this->fileExtension, '.')) {
            throw new \InvalidArgumentException(
                'Queue file extension must not contain ".".',
            );
        }

        if (!preg_match('/^[a-z0-9]+$/', $this->fileExtension)) {
            throw new \InvalidArgumentException(
                'Queue file extension contains invalid characters.',
            );
        }
    }

    /**
     * Vérifie les permissions UNIX.
     */
    private function validatePermissions(): void
    {
        if ($this->directoryPermissions <= 0) {
            throw new \InvalidArgumentException(
                'Directory permissions must be valid.',
            );
        }

        if ($this->filePermissions <= 0) {
            throw new \InvalidArgumentException(
                'File permissions must be valid.',
            );
        }
    }

    /**
     * Vérifie si un chemin est absolu (Unix ou Windows).
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix : /path/to/dir
        if (str_starts_with($path, '/')) {
            return true;
        }

        // Windows : C:\path ou C:/path
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1) {
            return true;
        }

        return false;
    }
}
