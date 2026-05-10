<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

/**
 * Gestionnaire des fichiers queue corrompus.
 *
 * Responsabilités :
 * - isoler les fichiers invalides
 * - déplacer les fichiers corrompus
 * - empêcher le blocage du consumer
 * - conserver les fichiers pour analyse
 *
 * Invariants :
 * - aucun fichier corrompu supprimé
 * - déplacement atomique uniquement
 * - conservation du nom original
 * - collisions gérées explicitement
 *
 * Cette classe ne doit jamais :
 * - parser le JSON
 * - faire de logique métier
 * - supprimer définitivement des fichiers
 * - dépendre de Symfony
 */
final readonly class CorruptedQueueFileManager
{
    public function __construct(
        private QueueConfiguration $configuration,
    ) {
    }

    /**
     * Déplace un fichier corrompu
     * vers le répertoire corrupted/.
     *
     * Le nom original est conservé
     * autant que possible.
     *
     * Exemple :
     *
     * 20260510_021522_a1b2c3d4.json
     *
     * →
     *
     * corrupted/20260510_021522_a1b2c3d4.json
     *
     * En cas de collision :
     *
     * corrupted/20260510_021522_a1b2c3d4_1.json
     *
     * @return string
     * Nouveau chemin du fichier déplacé.
     */
    public function move(
        string $sourceFile,
    ): string {
        $this->validateSourceFile($sourceFile);

        $this->ensureCorruptedDirectoryExists();

        $destinationFile = $this->generateDestinationPath(
            $sourceFile,
        );

        if (
            !@rename(
                $sourceFile,
                $destinationFile,
            )
        ) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to move corrupted queue file "%s" to "%s".',
                    $sourceFile,
                    $destinationFile,
                ),
            );
        }

        $this->applyFilePermissions($destinationFile);

        return $destinationFile;
    }

    /**
     * Vérifie la validité du fichier source.
     */
    private function validateSourceFile(
        string $sourceFile,
    ): void {
        if ('' === trim($sourceFile)) {
            throw new \InvalidArgumentException(
                'Corrupted queue source file cannot be empty.',
            );
        }

        if (!file_exists($sourceFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Corrupted queue source file "%s" does not exist.',
                    $sourceFile,
                ),
            );
        }

        if (!is_file($sourceFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Corrupted queue source path "%s" is not a file.',
                    $sourceFile,
                ),
            );
        }

        if (!is_readable($sourceFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Corrupted queue source file "%s" is not readable.',
                    $sourceFile,
                ),
            );
        }
    }

    /**
     * Vérifie l'existence du dossier corrupted/.
     */
    private function ensureCorruptedDirectoryExists(): void
    {
        $directory = $this->configuration->getCorruptedDirectory();

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
                    'Unable to create corrupted queue directory "%s".',
                    $directory,
                ),
            );
        }
    }

    /**
     * Génère le chemin final du fichier corrompu.
     *
     * Gère automatiquement les collisions.
     */
    private function generateDestinationPath(
        string $sourceFile,
    ): string {
        $directory = rtrim(
            $this->configuration->getCorruptedDirectory(),
            '/',
        );

        $filename = basename($sourceFile);

        $destination = sprintf(
            '%s/%s',
            $directory,
            $filename,
        );

        if (!file_exists($destination)) {
            return $destination;
        }

        return $this->generateCollisionSafePath(
            $directory,
            $filename,
        );
    }

    /**
     * Génère un chemin sans collision.
     *
     * Exemple :
     *
     * file.json
     * →
     * file_1.json
     * →
     * file_2.json
     */
    private function generateCollisionSafePath(
        string $directory,
        string $filename,
    ): string {
        $extension = pathinfo(
            $filename,
            PATHINFO_EXTENSION,
        );

        $baseName = pathinfo(
            $filename,
            PATHINFO_FILENAME,
        );

        $counter = 1;

        do {
            $candidate = sprintf(
                '%s/%s_%d.%s',
                $directory,
                $baseName,
                $counter,
                $extension,
            );

            ++$counter;
        } while (file_exists($candidate));

        return $candidate;
    }

    /**
     * Applique les permissions UNIX du fichier déplacé.
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
                    'Unable to apply permissions on corrupted queue file "%s".',
                    $file,
                ),
            );
        }
    }
}