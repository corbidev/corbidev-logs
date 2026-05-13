<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\CorruptedQueueFileManager;
use App\Queue\Infrastructure\QueueConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests fonctionnels de CorruptedQueueFileManager.
 */
final class CorruptedQueueFileManagerTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/corrupted_queue_manager_test_'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->baseDirectory);
    }

    /**
     * But : Vérifier que move() déplace un fichier corrompu vers le répertoire /corrupted/.
     *
     * Entrée : Fichier JSON invalide existant dans /logs/
     * Résultat attendu : Fichier source supprimé, destination dans /corrupted/, fichier existant
     */
    public function test_it_moves_corrupted_file(): void
    {
        $manager = $this->createManager();

        $sourceFile = $this->createQueueFile(
            'invalid.json',
            '{"invalid":}',
        );

        $destination = $manager->move($sourceFile);

        self::assertFileDoesNotExist($sourceFile);

        self::assertFileExists($destination);

        self::assertStringContainsString(
            '/corrupted/',
            $destination,
        );
    }

    /**
     * But : Vérifier que le nom de fichier d'origine est préservé dans la destination.
     *
     * Entrée : Fichier source nommé '20260510_021522_a1b2c3d4.json'
     * Résultat attendu : Chemin de destination se termine par ce nom
     */
    public function test_it_preserves_original_filename(): void
    {
        $manager = $this->createManager();

        $sourceFile = $this->createQueueFile(
            '20260510_021522_a1b2c3d4.json',
            'broken',
        );

        $destination = $manager->move($sourceFile);

        self::assertStringEndsWith(
            '20260510_021522_a1b2c3d4.json',
            $destination,
        );
    }

    /**
     * But : Vérifier que le contenu du fichier corrompu est conservé intégralement après déplacement.
     *
     * Entrée : Fichier avec contenu '{"broken": true'
     * Résultat attendu : file_get_contents(destination) = contenu original
     */
    public function test_it_preserves_file_content(): void
    {
        $manager = $this->createManager();

        $content = '{"broken": true';

        $sourceFile = $this->createQueueFile(
            'corrupted.json',
            $content,
        );

        $destination = $manager->move($sourceFile);

        self::assertSame(
            $content,
            file_get_contents($destination),
        );
    }

    /**
     * But : Vérifier que le répertoire /corrupted/ est créé automatiquement s'il n'existe pas.
     *
     * Entrée : Appel à move() sans répertoire /corrupted/ préexistant
     * Résultat attendu : baseDirectory/corrupted existe après move()
     */
    public function test_it_creates_corrupted_directory(): void
    {
        $manager = $this->createManager();

        $sourceFile = $this->createQueueFile(
            'broken.json',
            'invalid',
        );

        $manager->move($sourceFile);

        self::assertDirectoryExists(
            $this->baseDirectory . '/corrupted',
        );
    }

    /**
     * But : Vérifier que move() gère une collision de nom en renommant la destination.
     *
     * Entrée : /corrupted/test.json existe déjà, source nommée test.json
     * Résultat attendu : Destination renommée test_1.json, deux fichiers existent
     */
    public function test_it_handles_filename_collision(): void
    {
        $manager = $this->createManager();

        $existingFile = $this->baseDirectory
            . '/corrupted/test.json';

        @mkdir(
            dirname($existingFile),
            0775,
            true,
        );

        file_put_contents(
            $existingFile,
            'existing',
        );

        $sourceFile = $this->createQueueFile(
            'test.json',
            'broken',
        );

        $destination = $manager->move($sourceFile);

        self::assertStringEndsWith(
            'test_1.json',
            $destination,
        );

        self::assertFileExists($existingFile);
        self::assertFileExists($destination);
    }

    /**
     * But : Vérifier que les permissions Unix 0664 sont appliquées au fichier déplacé.
     *
     * Entrée : Appel move() sur Linux/macOS
     * Résultat attendu : fileperms() & 0777 = 0664 (test ignoré sur Windows)
     */
    public function test_it_applies_file_permissions(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Les permissions Unix (chmod) ne sont pas fiables sur Windows.');
        }

        $manager = $this->createManager();

        $sourceFile = $this->createQueueFile(
            'permissions.json',
            'broken',
        );

        $destination = $manager->move($sourceFile);

        $permissions = fileperms($destination) & 0777;

        self::assertSame(
            0664,
            $permissions,
        );
    }

    /**
     * But : Vérifier que move() retourne bien une chaîne de chemin non vide différente de la source.
     *
     * Entrée : Fichier source valide
     * Résultat attendu : destination est une string != $sourceFile
     */
    public function test_it_returns_destination_path(): void
    {
        $manager = $this->createManager();

        $sourceFile = $this->createQueueFile(
            'return.json',
            'broken',
        );

        $destination = $manager->move($sourceFile);

        self::assertIsString($destination);

        self::assertNotSame(
            $sourceFile,
            $destination,
        );
    }

    /**
     * But : Vérifier que 100 déplacements de fichiers distincts n'entrainent aucune collision.
     *
     * Entrée : 100 fichiers file_0.json...file_99.json
     * Résultat attendu : count(glob(corrupted/*.json)) = 100, chacun existant
     */
    public function test_it_moves_multiple_files_without_collision(): void
    {
        $manager = $this->createManager();

        for ($i = 0; $i < 100; ++$i) {
            $sourceFile = $this->createQueueFile(
                'file_' . $i . '.json',
                'broken',
            );

            $destination = $manager->move($sourceFile);

            self::assertFileExists($destination);
        }

        $files = glob(
            $this->baseDirectory
            . '/corrupted/*.json',
        );

        self::assertNotFalse($files);

        self::assertCount(
            100,
            $files,
        );
    }

    private function createManager(): CorruptedQueueFileManager
    {
        return new CorruptedQueueFileManager(
            new QueueConfiguration(
                baseDirectory: $this->baseDirectory,
            ),
        );
    }

    private function createQueueFile(
        string $filename,
        string $content,
    ): string {
        $directory = $this->baseDirectory . '/logs';

        @mkdir(
            $directory,
            0775,
            true,
        );

        $path = $directory . '/' . $filename;

        file_put_contents(
            $path,
            $content,
        );

        return $path;
    }

    private function removeDirectory(
        string $directory,
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());

                continue;
            }

            @unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}
