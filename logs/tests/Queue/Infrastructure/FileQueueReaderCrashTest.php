<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\CorruptedQueueFileManager;
use App\Queue\Infrastructure\FileQueueReader;
use App\Queue\Infrastructure\QueueConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de FileQueueReader.
 *
 * Objectifs :
 * - garantir l'isolation des corruptions
 * - empêcher le blocage de la queue
 * - sécuriser les erreurs disque
 * - garantir une lecture robuste
 */
final class FileQueueReaderCrashTest extends TestCase
{
    private string $baseDirectory;

    private QueueConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/file_queue_reader_crash_'
            . uniqid('', true);

        $this->configuration = new QueueConfiguration(
            baseDirectory: $this->baseDirectory,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeDirectory($this->baseDirectory);
    }

    public function test_it_moves_invalid_json_to_corrupted(): void
    {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        file_put_contents(
            $directory . '/broken.json',
            '{"invalid":}',
        );

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertCount(0, $results);

        $corruptedFiles = glob(
            $this->baseDirectory
            . '/corrupted/*.json',
        );

        self::assertNotFalse($corruptedFiles);

        self::assertCount(1, $corruptedFiles);
    }

    public function test_it_moves_empty_file_to_corrupted(): void
    {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        file_put_contents(
            $directory . '/empty.json',
            '',
        );

        $reader = $this->createReader();

        iterator_to_array(
            $reader->readBatch(),
        );

        $corruptedFiles = glob(
            $this->baseDirectory
            . '/corrupted/*.json',
        );

        self::assertNotFalse($corruptedFiles);

        self::assertCount(1, $corruptedFiles);
    }

    public function test_it_continues_batch_after_corrupted_file(): void
    {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        file_put_contents(
            $directory . '/0001_invalid.json',
            '{"broken":}',
        );

        file_put_contents(
            $directory . '/0002_valid.json',
            json_encode(
                ['message' => 'valid'],
                JSON_THROW_ON_ERROR,
            ),
        );

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertCount(1, $results);

        self::assertSame(
            'valid',
            $results[0]['payload']['message'],
        );
    }

    public function test_it_rejects_invalid_batch_limit(): void
    {
        $reader = $this->createReader();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        iterator_to_array(
            $reader->readBatch(0),
        );
    }

    public function test_it_rejects_empty_delete_path(): void
    {
        $reader = $this->createReader();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $reader->delete('');
    }

    public function test_it_rejects_empty_failed_path(): void
    {
        $reader = $this->createReader();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $reader->moveToFailed('');
    }

    public function test_it_rejects_empty_processing_path(): void
    {
        $reader = $this->createReader();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $reader->markAsProcessing('');
    }

    public function test_it_survives_massive_corrupted_queue(): void
    {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        for ($i = 0; $i < 100; ++$i) {
            file_put_contents(
                sprintf(
                    '%s/broken_%d.json',
                    $directory,
                    $i,
                ),
                '{"broken":}',
            );
        }

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(100),
        );

        self::assertCount(0, $results);

        $corruptedFiles = glob(
            $this->baseDirectory
            . '/corrupted/*.json',
        );

        self::assertNotFalse($corruptedFiles);

        self::assertCount(100, $corruptedFiles);
    }

    public function test_it_handles_invalid_utf8(): void
    {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        file_put_contents(
            $directory . '/utf8.json',
            "\xB1\x31",
        );

        $reader = $this->createReader();

        iterator_to_array(
            $reader->readBatch(),
        );

        $corruptedFiles = glob(
            $this->baseDirectory
            . '/corrupted/*.json',
        );

        self::assertNotFalse($corruptedFiles);

        self::assertCount(1, $corruptedFiles);
    }

    private function createReader(): FileQueueReader
    {
        return new FileQueueReader(
            $this->configuration,
            new CorruptedQueueFileManager(
                $this->configuration,
            ),
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