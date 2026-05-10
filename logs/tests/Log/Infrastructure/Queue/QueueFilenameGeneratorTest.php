<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @covers \App\Log\Infrastructure\Queue\QueueFilenameGenerator
 */
final class QueueFilenameGeneratorTest extends TestCase
{
    public function testGenerateReturnsString(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertIsString($filename);
    }

    public function testGenerateReturnsJsonFilename(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertStringEndsWith('.json', $filename);
    }

    public function testGenerateMatchesExpectedFormat(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertMatchesRegularExpression(
            '/^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/',
            $filename,
        );
    }

    public function testGenerateReturnsFilesystemSafeFilename(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertDoesNotMatchRegularExpression(
            '/[^a-zA-Z0-9_.-]/',
            $filename,
        );
    }

    public function testGenerateProducesUniqueFilenames(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 1000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            1000,
            array_unique($filenames),
        );
    }

    public function testGenerateProducesChronologicallySortableFilenames(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.000000');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 100; ++$i) {
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