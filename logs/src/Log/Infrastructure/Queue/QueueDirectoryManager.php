<?php

declare(strict_types=1);

namespace App\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use Throwable;

/**
 * Garantit qu’un dossier de queue est réellement utilisable.
 *
 * Responsabilités :
 * - création sécurisée
 * - validation filesystem
 * - validation permissions
 * - robustesse race conditions
 * - validation path
 *
 * Garanties :
 * - dossier existant
 * - dossier writable
 * - dossier readable
 * - dossier réellement utilisable
 *
 * Ce composant est idempotent.
 */
final class QueueDirectoryManager
{
    /**
     * Permissions par défaut des dossiers créés.
     */
    private const int DIRECTORY_PERMISSIONS = 0755;

    /**
     * Garantit qu’un dossier existe et est utilisable.
     *
     * @throws QueueDirectoryException
     */
    public function ensureDirectoryExists(string $directory): void
    {
        $this->assertValidDirectoryPath($directory);

        if ($this->directoryExists($directory)) {
            $this->assertDirectoryUsable($directory);

            return;
        }

        $this->createDirectory($directory);

        clearstatcache(true, $directory);

        if (!$this->directoryExists($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Le dossier "%s" n’existe pas après tentative de création.',
                    $directory,
                ),
            );
        }

        $this->assertDirectoryUsable($directory);
    }

    /**
     * Crée un dossier de manière sécurisée.
     *
     * @throws QueueDirectoryException
     */
    private function createDirectory(string $directory): void
    {
        try {
            $created = @mkdir(
                $directory,
                self::DIRECTORY_PERMISSIONS,
                true,
            );

            clearstatcache(true, $directory);

            /*
             * Important :
             * mkdir peut échouer alors qu’un autre process
             * a créé le dossier juste avant.
             */
            if ($created === false && !$this->directoryExists($directory)) {
                throw new QueueDirectoryException(
                    sprintf(
                        'Impossible de créer le dossier "%s".',
                        $directory,
                    ),
                );
            }
        } catch (QueueDirectoryException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new QueueDirectoryException(
                sprintf(
                    'Erreur lors de la création du dossier "%s" : %s',
                    $directory,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Vérifie qu’un dossier est réellement exploitable.
     *
     * @throws QueueDirectoryException
     */
    private function assertDirectoryUsable(string $directory): void
    {
        clearstatcache(true, $directory);

        if (!file_exists($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Le dossier "%s" n’existe pas.',
                    $directory,
                ),
            );
        }

        if (!is_dir($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Le chemin "%s" n’est pas un dossier.',
                    $directory,
                ),
            );
        }

        if (is_link($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Les liens symboliques sont interdits pour "%s".',
                    $directory,
                ),
            );
        }

        if (!is_readable($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Le dossier "%s" n’est pas lisible.',
                    $directory,
                ),
            );
        }

        if (!is_writable($directory)) {
            throw new QueueDirectoryException(
                sprintf(
                    'Le dossier "%s" n’est pas accessible en écriture.',
                    $directory,
                ),
            );
        }
    }

    /**
     * Vérifie qu’un dossier existe réellement.
     */
    private function directoryExists(string $directory): bool
    {
        clearstatcache(true, $directory);

        return is_dir($directory);
    }

    /**
     * Vérifie qu’un path de dossier est valide.
     *
     * @throws QueueDirectoryException
     */
    private function assertValidDirectoryPath(string $directory): void
    {
        $directory = trim($directory);

        if ($directory === '') {
            throw new QueueDirectoryException(
                'Le chemin du dossier est vide.',
            );
        }

        if ($directory === '.' || $directory === '..') {
            throw new QueueDirectoryException(
                'Le chemin du dossier est invalide.',
            );
        }

        if (str_contains($directory, "\0")) {
            throw new QueueDirectoryException(
                'Le chemin du dossier contient un caractère NULL byte.',
            );
        }

        if (mb_strlen($directory) > 4096) {
            throw new QueueDirectoryException(
                'Le chemin du dossier dépasse la limite autorisée.',
            );
        }
    }
}