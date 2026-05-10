<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\QueueConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests fonctionnels de QueueConfiguration.
 */
final class QueueConfigurationTest extends TestCase
{
    public function test_it_creates_valid_configuration(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/var/www/app/var/queue',
        );

        self::assertSame(
            '/var/www/app/var/queue',
            $configuration->getBaseDirectory(),
        );

        self::assertSame(
            '/var/www/app/var/queue/logs',
            $configuration->getQueueDirectory(),
        );

        self::assertSame(
            '/var/www/app/var/queue/processing',
            $configuration->getProcessingDirectory(),
        );

        self::assertSame(
            '/var/www/app/var/queue/corrupted',
            $configuration->getCorruptedDirectory(),
        );

        self::assertSame(
            '/var/www/app/var/queue/failed',
            $configuration->getFailedDirectory(),
        );

        self::assertSame(
            '/var/www/app/var/queue/tmp',
            $configuration->getTemporaryDirectory(),
        );

        self::assertSame(100, $configuration->getReadBatchSize());
        self::assertSame(3, $configuration->getMaxRetries());
        self::assertSame(1048576, $configuration->getMaxPayloadSize());
        self::assertSame('json', $configuration->getFileExtension());
        self::assertSame(0775, $configuration->getDirectoryPermissions());
        self::assertSame(0664, $configuration->getFilePermissions());
    }

    public function test_it_supports_custom_configuration(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/app/storage/queue',
            readBatchSize: 500,
            maxRetries: 10,
            maxPayloadSize: 2097152,
            queueDirectoryName: 'incoming',
            processingDirectoryName: 'running',
            corruptedDirectoryName: 'broken',
            failedDirectoryName: 'dead',
            temporaryDirectoryName: 'temp',
            fileExtension: 'queue',
            directoryPermissions: 0700,
            filePermissions: 0600,
        );

        self::assertSame(
            '/app/storage/queue/incoming',
            $configuration->getQueueDirectory(),
        );

        self::assertSame(
            '/app/storage/queue/running',
            $configuration->getProcessingDirectory(),
        );

        self::assertSame(
            '/app/storage/queue/broken',
            $configuration->getCorruptedDirectory(),
        );

        self::assertSame(
            '/app/storage/queue/dead',
            $configuration->getFailedDirectory(),
        );

        self::assertSame(
            '/app/storage/queue/temp',
            $configuration->getTemporaryDirectory(),
        );

        self::assertSame(500, $configuration->getReadBatchSize());
        self::assertSame(10, $configuration->getMaxRetries());
        self::assertSame(2097152, $configuration->getMaxPayloadSize());
        self::assertSame('queue', $configuration->getFileExtension());
        self::assertSame(0700, $configuration->getDirectoryPermissions());
        self::assertSame(0600, $configuration->getFilePermissions());
    }

    public function test_it_trims_trailing_slash_from_base_directory(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/var/queue/',
        );

        self::assertSame(
            '/var/queue/logs',
            $configuration->getQueueDirectory(),
        );
    }

    public function test_it_returns_absolute_directories(): void
    {
        $configuration = new QueueConfiguration(
            baseDirectory: '/opt/app/queue',
        );

        self::assertStringStartsWith(
            '/',
            $configuration->getQueueDirectory(),
        );

        self::assertStringStartsWith(
            '/',
            $configuration->getProcessingDirectory(),
        );

        self::assertStringStartsWith(
            '/',
            $configuration->getCorruptedDirectory(),
        );

        self::assertStringStartsWith(
            '/',
            $configuration->getFailedDirectory(),
        );
    }
}