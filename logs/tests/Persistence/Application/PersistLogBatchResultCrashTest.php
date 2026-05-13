<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Application;

use App\Persistence\Application\PersistLogBatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistLogBatchResult.
 *
 * OBJECTIFS :
 * ------------
 * Vérifier la robustesse face à :
 * - données hostiles
 * - UTF-8 invalide
 * - payload massif
 * - allocations importantes
 * - erreurs anormales
 *
 * IMPORTANT :
 * ------------
 * Le composant ne doit jamais :
 * - crasher
 * - exploser mémoire
 * - produire d'état incohérent
 */
final class PersistLogBatchResultCrashTest extends TestCase
{
    public function testItHandlesHugeErrorList(): void
    {
        $errors = [];

        for ($i = 0; $i < 10000; ++$i) {
            $errors[] = 'error-'.$i;
        }

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 10000,
            errors: $errors,
        );

        self::assertCount(10000, $result->getErrors());
    }

    public function testItHandlesHugeUnicodePayload(): void
    {
        $huge = str_repeat('🔥', 10000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$huge],
        );

        self::assertNotEmpty($result->getErrors());
    }

    public function testItHandlesBinaryPayload(): void
    {
        $binary = random_bytes(512);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$binary],
        );

        self::assertIsArray($result->getErrors());
    }

    public function testItHandlesInvalidUtf8(): void
    {
        $invalidUtf8 = "\xB1\x31";

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$invalidUtf8],
        );

        self::assertIsArray($result->getErrors());
    }

    public function testItHandlesVeryLargeMergeOperation(): void
    {
        $results = [];

        for ($i = 0; $i < 5000; ++$i) {
            $results[] = new PersistLogBatchResult(
                persistedCount: 1,
                failedCount: 1,
                errors: [
                    'error-'.$i,
                ],
            );
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(5000, $merged->getPersistedCount());
        self::assertSame(5000, $merged->getFailedCount());
        self::assertCount(5000, $merged->getErrors());
    }

    public function testItHandlesMassiveErrorMessage(): void
    {
        $payload = str_repeat('X', 10_000_000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$payload],
        );

        self::assertSame(
            1000,
            mb_strlen($result->getErrors()[0]),
        );
    }

    public function testItHandlesExtremeCounters(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertGreaterThan(0, $result->getPersistedCount());
        self::assertGreaterThan(0, $result->getFailedCount());
    }

    public function testItHandlesRecursiveMergeSafely(): void
    {
        $results = [];

        for ($i = 0; $i < 100; ++$i) {
            $inner = [];

            for ($j = 0; $j < 100; ++$j) {
                $inner[] = PersistLogBatchResult::success(1);
            }

            $results[] = PersistLogBatchResult::merge($inner);
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(10000, $merged->getPersistedCount());
    }

    public function testItHandlesNullByteInjection(): void
    {
        $payload = "error\0hidden";

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$payload],
        );

        self::assertNotEmpty($result->getErrors());
    }

    public function testItRemainsStableUnderRepeatedCalls(): void
    {
        $result = PersistLogBatchResult::success(42);

        for ($i = 0; $i < 10000; ++$i) {
            self::assertSame(42, $result->getPersistedCount());
            self::assertSame(0, $result->getFailedCount());
            self::assertFalse($result->hasFailures());

            $array = $result->toArray();

            self::assertSame(42, $array['persisted_count']);
        }
    }
}