<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\CorruptedQueueFileManager;
use App\Queue\Infrastructure\QueueConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de CorruptedQueueFileManager.
 *
 * Objectifs :
 * - sécuriser les erreurs disque
 * - empêcher les suppressions accidentelles
 * - tester les collisions massives
 * - garantir l'isolation des corruptions
 */
final class CorruptedQueueFileManagerCrashTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/corrupted_queue_manager_crash_'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->baseDirectory);
    }

    public function test_it_rejects_empty_source_file(): void
    {
        $manager = $this->createManager();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $manager->move('');
    }

    public function test_it_rejects_non_existing_file(): void
    {
        $manager = $this->createManager();

        $this->expectException(
            \RuntimeException::class,
        );

        $manager->move(
            '/does/not/exist.json',
        );
    }

    public function test_it_rejects_directory_instead_of_file(): void
    {
        $manager = $this->createManager();

        $directory = $this->baseDirectory
            . '/logs';

        @mkdir(
            $directory,
            0775,
            true,
        );

        $this->expectException(
            \RuntimeException::class,
        );

        $manager->move($directory);
    }

    public function test_it_fails_when_corrupted_directory_is_not_writable(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Les permissions Unix (chmod) ne sont pas supportées sur Windows.');
        }

        $sourceFile = $this->createQueueFile(
            'broken.json',
            'broken',
        );

        @mkdir(
            $this->baseDirectory . '/corrupted',
            0555,
            true,
        );

        $manager = $this->createManager();

        $this->expectException(
            \RuntimeException::class,
        );

        $manager->move($sourceFile);
    }

    public function test_it_survives_massive_collisions(): void
    {
        $manager = $this->createManager();

        @mkdir(
            $this->baseDirectory . '/corrupted',
            0775,
            true,
        );

        for ($i = 0; $i < 50; ++$i) {
            file_put_contents(
                $this->baseDirectory
                . '/corrupted/file_' . $i . '.json',
                'existing',
            );
        }

        for ($i = 0; $i < 50; ++$i) {
            $sourceFile = $this->createQueueFile(
                'file_' . $i . '.json',
                'broken',
            );

            $destination = $manager->move($sourceFile);

            self::assertFileExists($destination);
        }
    }

    public function test_it_never_deletes_corrupted_file(): void
    {
        $manager = $this->createManager();

        $content = '{"broken":';

        $sourceFile = $this->createQueueFile(
            'critical.json',
            $content,
        );

        $destination = $manager->move($sourceFile);

        self::assertFileExists($destination);

        self::assertSame(
            $content,
            file_get_contents($destination),
        );
    }

    public function test_it_handles_invalid_utf8_content(): void
    {
        $manager = $this->createManager();

        $content = "\xB1\x31";

        $sourceFile = $this->createQueueFile(
            'utf8.json',
            $content,
        );

        $destination = $manager->move($sourceFile);

        self::assertFileExists($destination);

        self::assertSame(
            $content,
            file_get_contents($destination),
        );
    }

    public function test_it_generates_unique_collision_names(): void
    {
        $manager = $this->createManager();

        @mkdir(
            $this->baseDirectory . '/corrupted',
            0775,
            true,
        );

        file_put_contents(
            $this->baseDirectory
            . '/corrupted/test.json',
            'existing',
        );

        file_put_contents(
            $this->baseDirectory
            . '/corrupted/test_1.json',
            'existing',
        );

        file_put_contents(
            $this->baseDirectory
            . '/corrupted/test_2.json',
            'existing',
        );

        $sourceFile = $this->createQueueFile(
            'test.json',
            'broken',
        );

        $destination = $manager->move($sourceFile);

        self::assertStringEndsWith(
            'test_3.json',
            $destination,
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