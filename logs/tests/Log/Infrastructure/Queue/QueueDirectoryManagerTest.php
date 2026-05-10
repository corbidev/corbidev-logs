<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Log\Infrastructure\Queue\QueueDirectoryManager
 */
final class QueueDirectoryManagerTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/queue_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDirectory)) {
            @rmdir($this->baseDirectory);
        }
    }

    public function testEnsureDirectoryExistsCreatesDirectory(): void
    {
        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($this->baseDirectory);

        self::assertDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsDoesNothingIfDirectoryAlreadyExists(): void
    {
        mkdir($this->baseDirectory);

        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($this->baseDirectory);

        self::assertDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsThrowsIfPathIsAFile(): void
    {
        file_put_contents($this->baseDirectory, 'test');

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsThrowsOnEmptyPath(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists('');
    }

    public function testEnsureDirectoryExistsThrowsOnDotPath(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists('.');
    }

    public function testEnsureDirectoryExistsThrowsOnNullByte(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists("invalid\0path");
    }
}