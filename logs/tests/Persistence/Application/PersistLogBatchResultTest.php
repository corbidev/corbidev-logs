<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Application;

use App\Persistence\Application\PersistLogBatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistLogBatchResult.
 *
 * OBJECTIFS :
 * ------------
 * - stabilité
 * - immutabilité
 * - prédictibilité
 * - robustesse
 * - invariants métier
 *
 * IMPORTANT :
 * ------------
 * Ces tests ne doivent JAMAIS :
 * - utiliser Symfony
 * - utiliser Doctrine
 * - utiliser une base SQL
 */
final class PersistLogBatchResultTest extends TestCase
{
    public function testItCreatesEmptyResult(): void
    {
        $result = PersistLogBatchResult::empty();

        self::assertSame(0, $result->getPersistedCount());
        self::assertSame(0, $result->getFailedCount());
        self::assertSame(0, $result->getTotalCount());

        self::assertFalse($result->hasFailures());
        self::assertTrue($result->hasNoFailures());
        self::assertFalse($result->hasPersistedLogs());

        self::assertSame([], $result->getErrors());
    }

    public function testItCreatesSuccessResult(): void
    {
        $result = PersistLogBatchResult::success(42);

        self::assertSame(42, $result->getPersistedCount());
        self::assertSame(0, $result->getFailedCount());
        self::assertSame(42, $result->getTotalCount());

        self::assertTrue($result->hasPersistedLogs());
        self::assertFalse($result->hasFailures());
        self::assertTrue($result->hasNoFailures());
    }

    public function testItCreatesFailureResult(): void
    {
        $result = PersistLogBatchResult::failure(
            failedCount: 12,
            errors: [
                'SQL error',
                'Deadlock',
            ],
        );

        self::assertSame(0, $result->getPersistedCount());
        self::assertSame(12, $result->getFailedCount());
        self::assertSame(12, $result->getTotalCount());

        self::assertTrue($result->hasFailures());
        self::assertFalse($result->hasNoFailures());

        self::assertCount(2, $result->getErrors());
    }

    public function testItReturnsStableArrayRepresentation(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 10,
            failedCount: 2,
            errors: ['error'],
        );

        self::assertSame(
            [
                'persisted_count' => 10,
                'failed_count' => 2,
                'total_count' => 12,
                'errors' => ['error'],
            ],
            $result->toArray(),
        );
    }

    public function testItMergesMultipleResults(): void
    {
        $result = PersistLogBatchResult::merge([
            new PersistLogBatchResult(
                persistedCount: 10,
                failedCount: 1,
                errors: ['error-1'],
            ),
            new PersistLogBatchResult(
                persistedCount: 20,
                failedCount: 2,
                errors: ['error-2'],
            ),
        ]);

        self::assertSame(30, $result->getPersistedCount());
        self::assertSame(3, $result->getFailedCount());
        self::assertSame(33, $result->getTotalCount());

        self::assertSame(
            [
                'error-1',
                'error-2',
            ],
            $result->getErrors(),
        );
    }

    public function testItRejectsNegativePersistedCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PersistLogBatchResult(
            persistedCount: -1,
            failedCount: 0,
        );
    }

    public function testItRejectsNegativeFailedCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: -1,
        );
    }

    public function testItRemovesDuplicateErrors(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 2,
            errors: [
                'duplicate',
                'duplicate',
                'duplicate',
            ],
        );

        self::assertSame(
            ['duplicate'],
            $result->getErrors(),
        );
    }

    public function testItIgnoresEmptyErrors(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [
                '',
                ' ',
                "\n",
                "\t",
                'valid',
            ],
        );

        self::assertSame(
            ['valid'],
            $result->getErrors(),
        );
    }

    public function testItSupportsStringableErrors(): void
    {
        $error = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-error';
            }
        };

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$error],
        );

        self::assertSame(
            ['stringable-error'],
            $result->getErrors(),
        );
    }

    public function testItIgnoresInvalidErrorTypes(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [
                [],
                new \stdClass(),
                fopen('php://memory', 'rb'),
                'valid',
            ],
        );

        self::assertSame(
            ['valid'],
            $result->getErrors(),
        );
    }

    public function testItTruncatesVeryLargeErrors(): void
    {
        $huge = str_repeat('A', 5000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$huge],
        );

        self::assertSame(
            1000,
            mb_strlen($result->getErrors()[0]),
        );
    }

    public function testItHandlesLargeMerge(): void
    {
        $results = [];

        for ($i = 0; $i < 1000; ++$i) {
            $results[] = new PersistLogBatchResult(
                persistedCount: 1,
                failedCount: 0,
            );
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(1000, $merged->getPersistedCount());
        self::assertSame(0, $merged->getFailedCount());
    }
}