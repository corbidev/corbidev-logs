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
    public function test_it_rejects_empty_base_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '',
        );
    }

    public function test_it_rejects_relative_base_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: 'var/queue',
        );
    }

    public function test_it_rejects_zero_batch_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            readBatchSize: 0,
        );
    }

    public function test_it_rejects_negative_batch_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            readBatchSize: -1,
        );
    }

    public function test_it_rejects_zero_max_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxRetries: 0,
        );
    }

    public function test_it_rejects_negative_max_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxRetries: -10,
        );
    }

    public function test_it_rejects_zero_payload_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxPayloadSize: 0,
        );
    }

    public function test_it_rejects_negative_payload_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            maxPayloadSize: -100,
        );
    }

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

    public function test_it_rejects_empty_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            fileExtension: '',
        );
    }

    public function test_it_rejects_extension_with_dot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            fileExtension: '.json',
        );
    }

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

    public function test_it_rejects_invalid_directory_permissions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            directoryPermissions: 0,
        );
    }

    public function test_it_rejects_invalid_file_permissions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueConfiguration(
            baseDirectory: '/var/queue',
            filePermissions: 0,
        );
    }

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