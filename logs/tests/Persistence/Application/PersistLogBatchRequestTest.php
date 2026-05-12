<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

use App\Persistence\Application\PersistLogBatchRequest;
use App\Tests\Shared\Factory\LogEntryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistLogBatchRequest.
 */
final class PersistLogBatchRequestTest extends TestCase
{
    public function testItStoresValidEntries(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertCount(
            2,
            $request->getEntries(),
        );

        self::assertSame(
            $entry1,
            $request->getEntries()[0],
        );

        self::assertSame(
            $entry2,
            $request->getEntries()[1],
        );
    }

    public function testItFiltersInvalidValues(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry,
            'invalid',
            123,
            null,
            new \stdClass(),
        ]);

        self::assertCount(
            1,
            $request->getEntries(),
        );

        self::assertSame(
            $entry,
            $request->getEntries()[0],
        );
    }

    public function testItReturnsEmptyBatch(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertTrue(
            $request->isEmpty(),
        );

        self::assertCount(
            0,
            $request->getEntries(),
        );
    }

    public function testItReturnsCorrectCount(): void
    {
        $request = new PersistLogBatchRequest([
            LogEntryFactory::create(),
            LogEntryFactory::create(),
            LogEntryFactory::create(),
        ]);

        self::assertSame(
            3,
            $request->count(),
        );
    }

    public function testItReturnsFirstEntry(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'first',
        );

        $entry2 = LogEntryFactory::create(
            message: 'second',
        );

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertSame(
            $entry1,
            $request->first(),
        );
    }

    public function testItReturnsLastEntry(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'first',
        );

        $entry2 = LogEntryFactory::create(
            message: 'second',
        );

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertSame(
            $entry2,
            $request->last(),
        );
    }

    public function testItReturnsNullFirstWhenEmpty(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertNull(
            $request->first(),
        );
    }

    public function testItReturnsNullLastWhenEmpty(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertNull(
            $request->last(),
        );
    }

    public function testItReindexesArrayKeys(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            50 => $entry1,
            99 => $entry2,
        ]);

        $entries = $request->getEntries();

        self::assertArrayHasKey(
            0,
            $entries,
        );

        self::assertArrayHasKey(
            1,
            $entries,
        );
    }

    public function testEntriesCannotBeModifiedExternally(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry,
        ]);

        $entries = $request->getEntries();

        $entries[] = LogEntryFactory::create();

        self::assertCount(
            1,
            $request->getEntries(),
        );
    }
}