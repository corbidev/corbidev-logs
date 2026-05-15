<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use App\Log\Infrastructure\Exception\QueueWriteException;
use App\Log\Infrastructure\Queue\FileQueueWriter;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Crash tests filesystem du FileQueueWriter.
 *
 * @covers \App\Log\Infrastructure\Queue\FileQueueWriter
 */
final class FileQueueWriterCrashTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/queue_crash_'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = glob($this->directory . '/*');

        if (is_array($files)) {
            foreach ($files as $file) {
                @chmod($file, 0644);

                @unlink($file);
            }
        }

        @chmod($this->directory, 0755);

        @rmdir($this->directory);
    }

    /**
     * But : Vérifier que write() lève une exception sur un JSON malformé.
     *
     * Entrée : Payload '{"invalid"' (JSON incomplet)
     * Résultat attendu : QueueWriteException est levée
     */
    public function testWriteFailsWithInvalidJson(): void
    {
        $writer = $this->createWriter();

        $this->expectException(QueueWriteException::class);

        $writer->write(
            $this->directory,
            '{"invalid"',
        );
    }

    /**
     * But : Vérifier que write() lève une exception sur un payload vide.
     *
     * Entrée : Chaîne vide ''
     * Résultat attendu : QueueWriteException est levée
     */
    public function testWriteFailsWithEmptyPayload(): void
    {
        $writer = $this->createWriter();

        $this->expectException(QueueWriteException::class);

        $writer->write(
            $this->directory,
            '',
        );
    }

    /**
     * But : Vérifier que write() lève une exception sur un payload UTF-8 invalide.
     *
     * Entrée : Payload "\xB1\x31" (séquence invalide)
     * Résultat attendu : QueueWriteException est levée
     */
    public function testWriteFailsWithInvalidUtf8(): void
    {
        $writer = $this->createWriter();

        $invalidUtf8 = "\xB1\x31";

        $this->expectException(QueueWriteException::class);

        $writer->write(
            $this->directory,
            $invalidUtf8,
        );
    }

    /**
     * But : Vérifier que 5 000 écritures successives créent bien 5 000 fichiers distincts.
     *
     * Entrée : 5 000 payloads JSON valides '{"message":"log_{i}"}'
     * Résultat attendu : 5 000 fichiers présents dans le répertoire de queue
     */
    public function testMassiveWritesRemainStable(): void
    {
        $writer = $this->createWriter();

        for ($i = 0; $i < 5000; ++$i) {
            $writer->write(
                $this->directory,
                sprintf(
                    '{"message":"log_%d"}',
                    $i,
                ),
            );
        }

        $files = glob($this->directory . '/*.json');

        self::assertIsArray($files);

        self::assertCount(5000, $files);
    }

    /**
     * But : Vérifier que write() échoue si le répertoire cible est en lecture seule.
     *
     * Entrée : Répertoire avec permissions 0555 (lecture seule)
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testWriteFailsOnReadonlyDirectory(): void
    {
        mkdir($this->directory);

        chmod($this->directory, 0555);

        clearstatcache(true, $this->directory);

        $writer = $this->createWriter();

        /*
         * Le FileQueueWriter délègue entièrement
         * la validation filesystem au
         * QueueDirectoryManager.
         *
         * Il est donc normal que l’exception
         * soit une QueueDirectoryException.
         */
        $this->expectException(
            QueueDirectoryException::class,
        );

        $writer->write(
            $this->directory,
            '{"message":"test"}',
        );
    }

    private function createWriter(): FileQueueWriter
    {
        $clock = new MockClock(
            '2026-05-10 01:30:15',
        );

        return new FileQueueWriter(
            new QueueDirectoryManager(),
            new QueueFilenameGenerator($clock),
        );
    }
}