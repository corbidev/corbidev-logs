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

    public function test_it_applies_file_permissions(): void
    {
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