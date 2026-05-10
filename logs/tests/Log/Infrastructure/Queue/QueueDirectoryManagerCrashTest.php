<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests filesystem du QueueDirectoryManager.
 *
 * @covers \App\Log\Infrastructure\Queue\QueueDirectoryManager
 */
final class QueueDirectoryManagerCrashTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/queue_crash_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDirectory)) {
            @chmod($this->baseDirectory, 0755);

            @rmdir($this->baseDirectory);
        }

        if (file_exists($this->baseDirectory)) {
            @unlink($this->baseDirectory);
        }
    }

    public function testMassiveEnsureDirectoryExistsCallsRemainStable(): void
    {
        $manager = new QueueDirectoryManager();

        for ($i = 0; $i < 1000; ++$i) {
            $manager->ensureDirectoryExists($this->baseDirectory);
        }

        self::assertDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsFailsOnFileCollision(): void
    {
        file_put_contents($this->baseDirectory, 'collision');

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsFailsOnUnreadableDirectory(): void
    {
        mkdir($this->baseDirectory);

        chmod($this->baseDirectory, 0000);

        clearstatcache(true, $this->baseDirectory);

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    public function testEnsureDirectoryExistsFailsOnSymlink(): void
    {
        $target = sys_get_temp_dir() . '/queue_target_' . uniqid();

        mkdir($target);

        symlink($target, $this->baseDirectory);

        $manager = new QueueDirectoryManager();

        try {
            $this->expectException(QueueDirectoryException::class);

            $manager->ensureDirectoryExists($this->baseDirectory);
        } finally {
            @unlink($this->baseDirectory);

            @rmdir($target);
        }
    }

    public function testEnsureDirectoryExistsSupportsNestedDirectories(): void
    {
        $nested = $this->baseDirectory . '/a/b/c/d/e';

        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($nested);

        self::assertDirectoryExists($nested);
    }
}