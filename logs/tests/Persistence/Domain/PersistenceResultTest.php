<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistenceResult.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité
 * - robustesse
 * - invariants métier
 * - immutabilité
 * - comportement prédictible
 * - sécurité des données
 *
 * IMPORTANT :
 * ------------
 * Ces tests ne doivent jamais :
 *
 * - utiliser Symfony
 * - utiliser Doctrine
 * - utiliser SQLite
 * - utiliser DatabaseTestCase
 *
 * PersistenceResult est un pur objet Domain immutable.
 */
final class PersistenceResultTest extends TestCase
{
    public function testItCreatesSuccessResult(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 15,
        );

        self::assertSame(
            15,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );

        self::assertSame(
            15,
            $result->getTotalCount(),
        );

        self::assertSame(
            100.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->hasPersistedLogs(),
        );

        self::assertFalse(
            $result->hasFailures(),
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    public function testItCreatesFailureResult(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 8,
            errors: [
                'sql error',
                'deadlock',
            ],
        );

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            8,
            $result->getFailedCount(),
        );

        self::assertSame(
            8,
            $result->getTotalCount(),
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );

        self::assertTrue(
            $result->hasFailures(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );

        self::assertSame(
            [
                'sql error',
                'deadlock',
            ],
            $result->getErrors(),
        );
    }

    public function testItCreatesPartialResult(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 7,
            failedCount: 3,
            errors: [
                'one failure',
            ],
        );

        self::assertSame(
            7,
            $result->getPersistedCount(),
        );

        self::assertSame(
            3,
            $result->getFailedCount(),
        );

        self::assertSame(
            10,
            $result->getTotalCount(),
        );

        self::assertSame(
            70.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->hasPersistedLogs(),
        );

        self::assertTrue(
            $result->hasFailures(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertTrue(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );
    }

    public function testItCreatesNothingToPersistResult(): void
    {
        $result = PersistenceResult::nothingToPersist();

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );

        self::assertSame(
            0,
            $result->getTotalCount(),
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->isNothingToPersist(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );

        self::assertFalse(
            $result->hasFailures(),
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    public function testItMergesResults(): void
    {
        $first = PersistenceResult::partial(
            persistedCount: 10,
            failedCount: 2,
            errors: [
                'error-1',
            ],
        );

        $second = PersistenceResult::partial(
            persistedCount: 5,
            failedCount: 3,
            errors: [
                'error-2',
            ],
        );

        $merged = $first->merge($second);

        self::assertSame(
            15,
            $merged->getPersistedCount(),
        );

        self::assertSame(
            5,
            $merged->getFailedCount(),
        );

        self::assertSame(
            20,
            $merged->getTotalCount(),
        );

        self::assertSame(
            75.0,
            $merged->getSuccessRate(),
        );

        self::assertSame(
            [
                'error-1',
                'error-2',
            ],
            $merged->getErrors(),
        );

        self::assertTrue(
            $merged->isPartial(),
        );

        self::assertFalse(
            $merged->isNothingToPersist(),
        );
    }

    public function testItReturnsZeroSuccessRateWhenEmpty(): void
    {
        $result = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->isNothingToPersist(),
        );
    }

    public function testItExportsArray(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 8,
            failedCount: 2,
            errors: [
                'timeout',
            ],
        );

        self::assertSame(
            [
                'persistedCount' => 8,
                'failedCount' => 2,
                'totalCount' => 10,
                'successRate' => 80.0,
                'isSuccess' => false,
                'isFailure' => false,
                'isPartial' => true,
                'isNothingToPersist' => false,
                'errors' => [
                    'timeout',
                ],
            ],
            $result->toArray(),
        );
    }

    public function testItDeduplicatesErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 3,
            errors: [
                'duplicate',
                'duplicate',
                'duplicate',
            ],
        );

        self::assertSame(
            [
                'duplicate',
            ],
            $result->getErrors(),
        );
    }

    public function testItTrimsErrorMessages(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                '   sql error   ',
            ],
        );

        self::assertSame(
            [
                'sql error',
            ],
            $result->getErrors(),
        );
    }

    public function testItRemovesEmptyErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 2,
            errors: [
                '',
                '   ',
                "\n",
                'valid',
            ],
        );

        self::assertSame(
            [
                'valid',
            ],
            $result->getErrors(),
        );
    }

    public function testItIgnoresNonScalarErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 3,
            errors: [
                new \stdClass(),
                [],
                fopen('php://memory', 'rb'),
                'valid',
            ],
        );

        self::assertSame(
            [
                'valid',
            ],
            $result->getErrors(),
        );
    }

    public function testItKeepsResultImmutable(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 1,
        );

        self::assertInstanceOf(
            PersistenceResult::class,
            $result,
        );
    }

    public function testItReturnsIndependentErrorsArray(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                'error',
            ],
        );

        $errors = $result->getErrors();

        $errors[] = 'modified';

        self::assertSame(
            [
                'error',
            ],
            $result->getErrors(),
        );
    }

    public function testItHandlesRoundedSuccessRate(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 1,
            failedCount: 3,
        );

        self::assertSame(
            25.0,
            $result->getSuccessRate(),
        );
    }

    public function testItHandlesZeroFailureSuccessCase(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 10,
            failedCount: 0,
        );

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isFailure(),
        );
    }

    public function testItHandlesZeroPersistedFailureCase(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 0,
            failedCount: 10,
        );

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    public function testItHandlesEmptyErrorsArray(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [],
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    public function testItHandlesOverflowProtection(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertSame(
            PHP_INT_MAX,
            $result->getTotalCount(),
        );
    }
}