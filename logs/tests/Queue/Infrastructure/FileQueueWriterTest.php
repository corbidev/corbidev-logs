<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\FileQueueWriter;
use App\Queue\Infrastructure\QueueConfiguration;
use App\Queue\Infrastructure\QueueFileNamingStrategy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests fonctionnels de FileQueueWriter.
 */
final class FileQueueWriterTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/queue_writer_test_'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->baseDirectory);
    }

    public function test_it_writes_valid_queue_file(): void
    {
        $writer = $this->createWriter();

        $payload = [
            'message' => 'Payment failed',
            'level' => 'error',
            'domain' => 'billing',
        ];

        $path = $writer->write($payload);

        self::assertFileExists($path);

        $content = file_get_contents($path);

        self::assertNotFalse($content);

        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            $payload,
            $decoded,
        );
    }

    public function test_it_creates_required_directories(): void
    {
        $writer = $this->createWriter();

        $writer->write([
            'message' => 'test',
        ]);

        self::assertDirectoryExists(
            $this->baseDirectory . '/logs',
        );

        self::assertDirectoryExists(
            $this->baseDirectory . '/processing',
        );

        self::assertDirectoryExists(
            $this->baseDirectory . '/corrupted',
        );

        self::assertDirectoryExists(
            $this->baseDirectory . '/failed',
        );

        self::assertDirectoryExists(
            $this->baseDirectory . '/tmp',
        );
    }

    public function test_it_generates_json_file(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write([
            'message' => 'hello',
        ]);

        self::assertStringEndsWith(
            '.json',
            $path,
        );
    }

    public function test_it_writes_valid_json(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write([
            'message' => 'UTF-8 éèà',
        ]);

        $content = file_get_contents($path);

        self::assertNotFalse($content);

        json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
        );
    }

    public function test_it_applies_file_permissions(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write([
            'message' => 'permissions',
        ]);

        $permissions = fileperms($path) & 0777;

        self::assertSame(
            0664,
            $permissions,
        );
    }

    public function test_it_generates_unique_files(): void
    {
        $writer = $this->createWriter();

        $paths = [];

        for ($i = 0; $i < 100; ++$i) {
            $paths[] = $writer->write([
                'message' => 'test-' . $i,
            ]);
        }

        self::assertCount(
            100,
            array_unique($paths),
        );
    }

    public function test_it_preserves_payload_integrity(): void
    {
        $writer = $this->createWriter();

        $payload = [
            'message' => 'Integrity test',
            'context' => [
                'user' => 123,
                'tags' => ['a', 'b', 'c'],
            ],
        ];

        $path = $writer->write($payload);

        $content = file_get_contents($path);

        self::assertNotFalse($content);

        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            $payload,
            $decoded,
        );
    }

    public function test_it_writes_multiple_files_without_collision(): void
    {
        $writer = $this->createWriter();

        for ($i = 0; $i < 500; ++$i) {
            $writer->write([
                'message' => 'bulk-' . $i,
            ]);
        }

        $files = glob(
            $this->baseDirectory . '/logs/*.json',
        );

        self::assertNotFalse($files);

        self::assertCount(
            500,
            $files,
        );
    }

    public function test_it_does_not_leave_temporary_files_after_success(): void
    {
        $writer = $this->createWriter();

        $writer->write([
            'message' => 'cleanup',
        ]);

        $temporaryFiles = glob(
            $this->baseDirectory . '/tmp/*',
        );

        self::assertNotFalse($temporaryFiles);

        self::assertCount(
            0,
            $temporaryFiles,
        );
    }

    private function createWriter(
        int $maxPayloadSize = 1048576,
    ): FileQueueWriter {
        return new FileQueueWriter(
            new QueueConfiguration(
                baseDirectory: $this->baseDirectory,
                maxPayloadSize: $maxPayloadSize,
            ),
            new QueueFileNamingStrategy(),
        );
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
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($directory);
    }
}