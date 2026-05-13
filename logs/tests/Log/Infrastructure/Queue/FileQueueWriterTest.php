<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Queue\FileQueueWriter;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @covers \App\Log\Infrastructure\Queue\FileQueueWriter
 */
final class FileQueueWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/queue_writer_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = glob($this->directory . '/*');

        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->directory);
    }

    /**
     * But : Vérifier que write() crée bien un fichier de queue sur le disque.
     *
     * Entrée : Payload JSON valide '{"message":"test"}'
     * Résultat attendu : Le fichier existe après l'écriture
     */
    public function testWriteCreatesQueueFile(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write(
            $this->directory,
            '{"message":"test"}',
        );

        self::assertFileExists($path);
    }

    /**
     * But : Vérifier que le contenu du fichier correspond exactement au payload fourni.
     *
     * Entrée : Payload '{"message":"hello"}'
     * Résultat attendu : file_get_contents() retourne le payload identique
     */
    public function testWriteStoresExpectedContent(): void
    {
        $writer = $this->createWriter();

        $payload = '{"message":"hello"}';

        $path = $writer->write(
            $this->directory,
            $payload,
        );

        self::assertSame(
            $payload,
            file_get_contents($path),
        );
    }

    /**
     * But : Vérifier que le fichier créé a l'extension '.json'.
     *
     * Entrée : Payload JSON valide
     * Résultat attendu : Le chemin retourné se termine par '.json'
     */
    public function testWriteCreatesJsonFile(): void
    {
        $writer = $this->createWriter();

        $path = $writer->write(
            $this->directory,
            '{"message":"test"}',
        );

        self::assertStringEndsWith('.json', $path);
    }

    /**
     * But : Vérifier qu'aucun fichier temporaire '.tmp' n'est laissé après une écriture réussie.
     *
     * Entrée : Payload JSON valide
     * Résultat attendu : Aucun fichier *.tmp dans le répertoire de queue
     */
    public function testWriteNeverLeavesTemporaryFile(): void
    {
        $writer = $this->createWriter();

        $writer->write(
            $this->directory,
            '{"message":"test"}',
        );

        $temporaryFiles = glob($this->directory . '/*.tmp');

        self::assertEmpty($temporaryFiles);
    }

    private function createWriter(): FileQueueWriter
    {
        $clock = new MockClock('2026-05-10 01:30:15');

        return new FileQueueWriter(
            new QueueDirectoryManager(),
            new QueueFilenameGenerator($clock),
        );
    }
}