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
 * Crash tests de FileQueueWriter.
 *
 * Objectifs :
 * - garantir l'absence de fichiers partiels
 * - sécuriser les erreurs disque
 * - tester les payloads hostiles
 * - valider le cleanup des temporaires
 */
final class FileQueueWriterCrashTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/queue_writer_crash_'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->baseDirectory);
    }

    public function test_it_rejects_payload_exceeding_max_size(): void
    {
        $writer = $this->createWriter(
            maxPayloadSize: 100,
        );

        $this->expectException(\RuntimeException::class);

        $writer->write([
            'message' => str_repeat('A', 1000),
        ]);
    }

    public function test_it_fails_when_base_directory_is_not_writable(): void
    {
        mkdir($this->baseDirectory, 0555, true);

        $writer = new FileQueueWriter(
            new QueueConfiguration(
                baseDirectory: $this->baseDirectory,
            ),
            new QueueFileNamingStrategy(),
        );

        $this->expectException(\RuntimeException::class);

        $writer->write([
            'message' => 'disk failure',
        ]);
    }

    public function test_it_handles_massive_payload(): void
    {
        $writer = $this->createWriter(
            maxPayloadSize: 10 * 1024 * 1024,
        );

        $payload = [
            'message' => str_repeat(
                'X',
                1024 * 1024,
            ),
        ];

        $path = $writer->write($payload);

        self::assertFileExists($path);
    }

    public function test_it_never_creates_partial_final_file_on_failure(): void
    {
        mkdir($this->baseDirectory, 0555, true);

        $writer = new FileQueueWriter(
            new QueueConfiguration(
                baseDirectory: $this->baseDirectory,
            ),
            new QueueFileNamingStrategy(),
        );

        try {
            $writer->write([
                'message' => 'failure',
            ]);
        } catch (\Throwable) {
        }

        $files = glob(
            $this->baseDirectory . '/logs/*.json',
        );

        self::assertTrue(
            false === $files || [] === $files,
        );
    }

    public function test_it_never_leaves_invalid_json(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write([
            'message' => 'valid',
            'utf8' => "\xB1\x31",
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

    public function test_it_survives_high_frequency_writes(): void
    {
        $writer = $this->createWriter();

        for ($i = 0; $i < 2000; ++$i) {
            $path = $writer->write([
                'message' => 'stress-' . $i,
            ]);

            self::assertFileExists($path);
        }

        $files = glob(
            $this->baseDirectory . '/logs/*.json',
        );

        self::assertNotFalse($files);

        self::assertCount(
            2000,
            $files,
        );
    }

    public function test_it_never_leaves_temporary_files_after_failure(): void
    {
        mkdir($this->baseDirectory, 0555, true);

        $writer = new FileQueueWriter(
            new QueueConfiguration(
                baseDirectory: $this->baseDirectory,
            ),
            new QueueFileNamingStrategy(),
        );

        try {
            $writer->write([
                'message' => 'failure',
            ]);
        } catch (\Throwable) {
        }

        $temporaryFiles = glob(
            $this->baseDirectory . '/tmp/*',
        );

        self::assertTrue(
            false === $temporaryFiles || [] === $temporaryFiles,
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
                @rmdir($file->getPathname());

                continue;
            }

            @unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}