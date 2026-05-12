<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

use App\Persistence\Application\PersistLogBatchRequest;
use App\Tests\Shared\Factory\LogEntryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistLogBatchRequest.
 */
final class PersistLogBatchRequestCrashTest extends TestCase
{
    public function testItSurvivesHugeArray(): void
    {
        $request = new PersistLogBatchRequest(
            LogEntryFactory::many(10000),
        );

        self::assertSame(
            10000,
            $request->count(),
        );
    }

    public function testItSurvivesMixedHostilePayload(): void
    {
        $payload = [
            null,
            false,
            true,
            123,
            12.5,
            '',
            [],
            new \stdClass(),
            LogEntryFactory::create(),
        ];

        $request = new PersistLogBatchRequest(
            $payload,
        );

        self::assertSame(
            1,
            $request->count(),
        );
    }

    public function testItSurvivesGeneratorInput(): void
    {
        $entry = LogEntryFactory::create();

        $generator = static function (
            mixed $entry,
        ): \Generator {
            yield new \stdClass();
            yield 'invalid';
            yield 123;
            yield null;
            yield $entry;
        };

        $request = new PersistLogBatchRequest(
            $generator($entry),
        );

        self::assertSame(
            1,
            $request->count(),
        );
    }

    public function testItSurvivesCorruptedKeys(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            PHP_INT_MAX => $entry,
            -500 => $entry,
            'foo' => $entry,
        ]);

        self::assertSame(
            3,
            $request->count(),
        );
    }

    public function testItSurvivesNestedArrays(): void
    {
        $request = new PersistLogBatchRequest([
            [
                [
                    [
                        'invalid',
                    ],
                ],
            ],
        ]);

        self::assertTrue(
            $request->isEmpty(),
        );
    }

    public function testItSurvivesMassiveInvalidPayload(): void
    {
        $payload = [];

        for ($i = 0; $i < 5000; ++$i) {
            $payload[] = new \stdClass();
        }

        $request = new PersistLogBatchRequest(
            $payload,
        );

        self::assertTrue(
            $request->isEmpty(),
        );
    }

    public function testItSurvivesDuplicatedEntries(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry,
            $entry,
            $entry,
        ]);

        self::assertSame(
            3,
            $request->count(),
        );
    }
}