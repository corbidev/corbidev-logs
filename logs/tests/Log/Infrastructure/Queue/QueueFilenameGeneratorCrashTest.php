<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Crash tests robustesse du générateur.
 *
 * @covers \App\Log\Infrastructure\Queue\QueueFilenameGenerator
 */
final class QueueFilenameGeneratorCrashTest extends TestCase
{
    public function testMassiveGenerationDoesNotCreateCollisions(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 50000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            50000,
            array_unique($filenames),
        );
    }

    public function testGenerationWithSameTimestampDoesNotCollide(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 10000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            10000,
            array_unique($filenames),
        );
    }

    public function testGeneratedFilenamesRemainFilesystemSafe(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        for ($i = 0; $i < 10000; ++$i) {
            $filename = $generator->generate();

            self::assertMatchesRegularExpression(
                '/^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/',
                $filename,
            );
        }
    }

    public function testGeneratedFilenamesRemainShortEnough(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        for ($i = 0; $i < 10000; ++$i) {
            $filename = $generator->generate();

            self::assertLessThanOrEqual(
                255,
                mb_strlen($filename),
            );
        }
    }

    public function testGeneratedFilenamesRemainChronologicallySortable(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.000000');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 1000; ++$i) {
            $filenames[] = $generator->generate();

            $clock->sleep(0.001);
        }

        $sorted = $filenames;

        sort($sorted);

        self::assertSame(
            $sorted,
            $filenames,
        );
    }
}