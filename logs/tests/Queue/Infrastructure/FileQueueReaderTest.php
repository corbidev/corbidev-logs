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
 * Tests fonctionnels de FileQueueReader.
 */
final class FileQueueReaderTest extends TestCase
{
    private string $baseDirectory;

    private QueueConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir()
            . '/file_queue_reader_test_'
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

    /**
     * But : Vérifier que readBatch() lit et retourne les fichiers de la queue dans l'ordre.
     *
     * Entrée : Deux fichiers JSON dans le répertoire de queue
     * Résultat attendu : 2 résultats avec les messages 'A' et 'B'
     */
    public function test_it_reads_queue_batch(): void
    {
        $this->createQueueFile(
            '20260510_000001_test.json',
            ['message' => 'A'],
        );

        $this->createQueueFile(
            '20260510_000002_test.json',
            ['message' => 'B'],
        );

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertCount(2, $results);

        self::assertSame(
            'A',
            $results[0]['payload']['message'],
        );

        self::assertSame(
            'B',
            $results[1]['payload']['message'],
        );
    }

    /**
     * But : Vérifier que readBatch() limite le nombre de fichiers lus au paramètre donné.
     *
     * Entrée : 10 fichiers dans la queue, readBatch(3)
     * Résultat attendu : 3 résultats retournés
     */
    public function test_it_limits_batch_size(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createQueueFile(
                sprintf(
                    '20260510_00000%d_test.json',
                    $i,
                ),
                ['message' => 'msg'],
            );
        }

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(3),
        );

        self::assertCount(3, $results);
    }

    /**
     * But : Vérifier que readBatch() retourne un tableau vide si la queue est vide.
     *
     * Entrée : Aucun fichier dans le répertoire de queue
     * Résultat attendu : []
     */
    public function test_it_returns_empty_batch_when_queue_is_empty(): void
    {
        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertSame([], $results);
    }

    /**
     * But : Vérifier que readBatch() déplace les fichiers vers le répertoire 'processing'.
     *
     * Entrée : Un fichier JSON dans la queue
     * Résultat attendu : Le fichier source n'existe plus, le chemin retourné contient '/processing/'
     */
    public function test_it_moves_files_to_processing(): void
    {
        $file = $this->createQueueFile(
            'test.json',
            ['message' => 'ok'],
        );

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertFileDoesNotExist($file);

        self::assertFileExists(
            $results[0]['path'],
        );

        self::assertStringContainsString(
            '/processing/',
            $results[0]['path'],
        );
    }

    /**
     * But : Vérifier que delete() supprime bien le fichier du répertoire 'processing'.
     *
     * Entrée : Un fichier dans le répertoire 'processing'
     * Résultat attendu : Le fichier n'existe plus
     */
    public function test_it_deletes_processed_file(): void
    {
        $file = $this->createProcessingFile(
            'delete.json',
            ['message' => 'ok'],
        );

        $reader = $this->createReader();

        $reader->delete($file);

        self::assertFileDoesNotExist($file);
    }

    /**
     * But : Vérifier que moveToFailed() déplace un fichier vers le répertoire 'failed'.
     *
     * Entrée : Un fichier dans 'processing'
     * Résultat attendu : Le fichier source supprimé, destination contient '/failed/'
     */
    public function test_it_moves_file_to_failed(): void
    {
        $file = $this->createProcessingFile(
            'failed.json',
            ['message' => 'fail'],
        );

        $reader = $this->createReader();

        $destination = $reader->moveToFailed($file);

        self::assertFileDoesNotExist($file);

        self::assertFileExists($destination);

        self::assertStringContainsString(
            '/failed/',
            $destination,
        );
    }

    /**
     * But : Vérifier que readBatch() respecte l'ordre FIFO approximatif par nom de fichier.
     *
     * Entrée : Fichiers '_000001_a.json' et '_000002_b.json'
     * Résultat attendu : Premier résultat='first', deuxième='second'
     */
    public function test_it_reads_files_in_fifo_approximate_order(): void
    {
        $this->createQueueFile(
            '20260510_000001_a.json',
            ['message' => 'first'],
        );

        $this->createQueueFile(
            '20260510_000002_b.json',
            ['message' => 'second'],
        );

        $reader = $this->createReader();

        $results = iterator_to_array(
            $reader->readBatch(),
        );

        self::assertSame(
            'first',
            $results[0]['payload']['message'],
        );

        self::assertSame(
            'second',
            $results[1]['payload']['message'],
        );
    }

    /**
     * But : Vérifier que readBatch() retourne un Generator (lecture lazy) pour 100 fichiers.
     *
     * Entrée : 100 fichiers JSON dans la queue
     * Résultat attendu : readBatch() retourne une instance de \Generator
     */
    public function test_it_uses_generator_for_memory_bounded_reads(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $this->createQueueFile(
                sprintf(
                    '20260510_0000%02d_test.json',
                    $i,
                ),
                ['message' => 'msg'],
            );
        }

        $reader = $this->createReader();

        $generator = $reader->readBatch();

        self::assertInstanceOf(
            \Generator::class,
            $generator,
        );
    }

    /**
     * But : Vérifier que readBatch() crée le répertoire 'processing' s'il n'existe pas.
     *
     * Entrée : Un fichier dans la queue, répertoire 'processing' absent
     * Résultat attendu : Le répertoire 'processing' existe après l'appel
     */
    public function test_it_creates_processing_directory(): void
    {
        $this->createQueueFile(
            'processing.json',
            ['message' => 'ok'],
        );

        $reader = $this->createReader();

        iterator_to_array(
            $reader->readBatch(),
        );

        self::assertDirectoryExists(
            $this->baseDirectory . '/processing',
        );
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

    /**
     * @param array<string, mixed> $payload
     */
    private function createQueueFile(
        string $filename,
        array $payload,
    ): string {
        $directory = $this->baseDirectory . '/logs';

        @mkdir($directory, 0775, true);

        $path = $directory . '/' . $filename;

        file_put_contents(
            $path,
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR,
            ),
        );

        return $path;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createProcessingFile(
        string $filename,
        array $payload,
    ): string {
        $directory = $this->baseDirectory . '/processing';

        @mkdir($directory, 0775, true);

        $path = $directory . '/' . $filename;

        file_put_contents(
            $path,
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR,
            ),
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