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

    /**
     * But : Vérifier que write() lève \RuntimeException si le payload dépasse maxPayloadSize.
     *
     * Entrée : maxPayloadSize=100, payload de 1 000 caractères
     * Résultat attendu : \RuntimeException lancée
     */
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

    /**
     * But : Vérifier que write() échoue si le répertoire de base n'est pas accessible en écriture.
     *
     * Entrée : Répertoire créé avec permissions 0555 (Linux/macOS uniquement)
     * Résultat attendu : \RuntimeException lancée
     */
    public function test_it_fails_when_base_directory_is_not_writable(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Les permissions Unix (chmod) ne sont pas supportées sur Windows.');
        }

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

    /**
     * But : Vérifier qu'un payload de 1 Mo est accepté et persiste correctement.
     *
     * Entrée : maxPayloadSize=10*1024*1024, message de 1 048 576 caractères
     * Résultat attendu : Fichier créé et existant
     */
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

    /**
     * But : Vérifier qu'aucun fichier final partiel n'est créé en cas d'échec d'écriture.
     *
     * Entrée : Répertoire non accessible en écriture (Linux/macOS)
     * Résultat attendu : Aucun fichier dans /logs/*.json
     */
    public function test_it_never_creates_partial_final_file_on_failure(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Les permissions Unix (chmod) ne sont pas supportées sur Windows.');
        }

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

    /**
     * But : Vérifier que le JSON produit est toujours valide même avec du UTF-8 invalide dans le payload.
     *
     * Entrée : payload avec 'utf8' => "\xB1\x31"
     * Résultat attendu : json_last_error() = JSON_ERROR_NONE
     */
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

    /**
     * But : Vérifier que 2 000 écritures successives ne causent pas de crash ou de collision.
     *
     * Entrée : 2 000 appels à write() avec messages distincts
     * Résultat attendu : count(glob(logs/*.json)) = 2000, tous les fichiers existent
     */
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

    /**
     * But : Vérifier qu'aucun fichier temporaire n'est laissé dans /tmp/ après un échec.
     *
     * Entrée : Répertoire non accessible en écriture
     * Résultat attendu : /tmp/* est vide ou n'existe pas
     */
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
