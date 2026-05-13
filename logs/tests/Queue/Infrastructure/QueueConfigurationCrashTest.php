<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\QueueConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de QueueConfiguration.
 *
 * Objectif :
 * vérifier que toute configuration invalide
 * est rejetée explicitement.
 */
final class QueueConfigurationCrashTest extends TestCase
{
    /**
     * But : Vérifier qu'un baseDirectory vide lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory=''
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_empty_base_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '',
        );
    }

    /**
     * But : Vérifier qu'un chemin relatif comme baseDirectory lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='var/queue'
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_relative_base_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: 'var/queue',
        );
    }

    /**
     * But : Vérifier qu'un readBatchSize=0 lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', readBatchSize=0
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_zero_batch_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            readBatchSize: 0,
        );
    }

    /**
     * But : Vérifier qu'un readBatchSize négatif lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', readBatchSize=-1
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_negative_batch_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            readBatchSize: -1,
        );
    }

    /**
     * But : Vérifier qu'un maxRetries=0 lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', maxRetries=0
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_zero_max_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxRetries: 0,
        );
    }

    /**
     * But : Vérifier qu'un maxRetries négatif lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', maxRetries=-10
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_negative_max_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxRetries: -10,
        );
    }

    /**
     * But : Vérifier qu'un maxPayloadSize=0 lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', maxPayloadSize=0
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_zero_payload_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxPayloadSize: 0,
        );
    }

    /**
     * But : Vérifier qu'un maxPayloadSize négatif lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', maxPayloadSize=-100
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_negative_payload_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxPayloadSize: -100,
        );
    }

    /**
     * But : Vérifier que les noms de répertoire de queue invalides lèvent une InvalidArgumentException.
     *
     * Entrée : queueDirectoryName parmi '', '   ', '../logs', 'logs/test' (DataProvider)
     * Résultat attendu : \InvalidArgumentException lancée pour chaque cas
     */
    #[DataProvider('invalidDirectoryProvider')]
    public function test_it_rejects_invalid_directory_names(
        string $directory,
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            queueDirectoryName: $directory,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidDirectoryProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'parent traversal' => ['../logs'];
        yield 'contains slash' => ['logs/test'];
    }

    /**
     * But : Vérifier qu'une extension de fichier vide lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', fileExtension=''
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_empty_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            fileExtension: '',
        );
    }

    /**
     * But : Vérifier qu'une extension contenant un point lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', fileExtension='.json'
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_extension_with_dot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            fileExtension: '.json',
        );
    }

    /**
     * But : Vérifier que les extensions avec caractères invalides lèvent une InvalidArgumentException.
     *
     * Entrée : fileExtension parmi 'json file', 'json/test', 'json!', 'ééé' (DataProvider)
     * Résultat attendu : \InvalidArgumentException lancée pour chaque cas
     */
    #[DataProvider('invalidExtensionProvider')]
    public function test_it_rejects_invalid_extension_characters(
        string $extension,
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            fileExtension: $extension,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidExtensionProvider(): iterable
    {
        yield 'space' => ['json file'];
        yield 'slash' => ['json/test'];
        yield 'special chars' => ['json!'];
        yield 'unicode' => ['ééé'];
    }

    /**
     * But : Vérifier que directoryPermissions=0 lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', directoryPermissions=0
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_invalid_directory_permissions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            directoryPermissions: 0,
        );
    }

    /**
     * But : Vérifier que filePermissions=0 lève une InvalidArgumentException.
     *
     * Entrée : baseDirectory='/var/queue', filePermissions=0
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function test_it_rejects_invalid_file_permissions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            filePermissions: 0,
        );
    }

    /**
     * But : Vérifier qu'une limite de payload de 50 Mo est acceptée et stockée correctement.
     *
     * Entrée : maxPayloadSize=1024*1024*50
     * Résultat attendu : getMaxPayloadSize() = 52428800
     */
    public function test_it_supports_large_payload_limit(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxPayloadSize: 1024 * 1024 * 50,
        );

        self::assertSame(
            52428800,
            $configuration->getMaxPayloadSize(),
        );
    }

    /**
     * But : Vérifier qu'une taille de lot de 10 000 est acceptée et stockée correctement.
     *
     * Entrée : readBatchSize=10000
     * Résultat attendu : getReadBatchSize() = 10000
     */
    public function test_it_supports_large_batch_size(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/var/queue',
            readBatchSize: 10000,
        );

        self::assertSame(
            10000,
            $configuration->getReadBatchSize(),
        );
    }
}