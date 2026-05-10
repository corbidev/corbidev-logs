<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Constantes\PersistenceLimits;
use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistenceResult.
 *
 * Objectifs :
 * - robustesse
 * - stabilité
 * - protection mémoire
 * - payloads hostiles
 * - overflow
 * - corruption données
 *
 * IMPORTANT :
 * Aucun accès DB ici.
 *
 * Les crash tests doivent rester :
 * - rapides
 * - déterministes
 * - isolés
 */
final class PersistenceResultCrashTest extends TestCase
{
    public function testItRejectsNegativePersistedCount(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        new PersistenceResult(
            persistedCount: -1,
            failedCount: 0,
        );
    }

    public function testItRejectsNegativeFailedCount(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        new PersistenceResult(
            persistedCount: 0,
            failedCount: -1,
        );
    }

    public function testItIgnoresInvalidErrorTypes(): void
    {
        $resource = fopen(
            'php://memory',
            'rb',
        );

        self::assertIsResource($resource);

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                new \stdClass(),
                $resource,
                null,
                [],
                true,
                123,
                'valid-error',
            ],
        );

        fclose($resource);

        self::assertSame(
            [
                '1',
                '123',
                'valid-error',
            ],
            $result->getErrors(),
        );
    }

    public function testItIgnoresEmptyErrors(): void
    {
        $result = PersistenceResult::failure(
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
            [
                'valid',
            ],
            $result->getErrors(),
        );
    }

    public function testItLimitsErrorSize(): void
    {
        $huge = str_repeat(
            'A',
            10000,
        );

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $huge,
            ],
        );

        self::assertSame(
            PersistenceLimits::MAX_ERROR_LENGTH,
            mb_strlen(
                $result->getErrors()[0],
            ),
        );
    }

    public function testItLimitsErrorCollectionSize(): void
    {
        $errors = [];

        for ($i = 0; $i < 10000; ++$i) {
            $errors[] = 'error-' . $i;
        }

        $result = PersistenceResult::failure(
            failedCount: 10000,
            errors: $errors,
        );

        self::assertCount(
            PersistenceLimits::MAX_ERRORS,
            $result->getErrors(),
        );
    }

    public function testItHandlesUtf8HostilePayloads(): void
    {
        $payload = "\xB1\x31";

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $payload,
            ],
        );

        self::assertCount(
            1,
            $result->getErrors(),
        );
    }

    public function testItHandlesMassiveMergeWithoutCrash(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 0,
        );

        for ($i = 0; $i < 5000; ++$i) {
            $result = $result->merge(
                PersistenceResult::partial(
                    persistedCount: 1,
                    failedCount: 1,
                    errors: [
                        'error-' . $i,
                    ],
                ),
            );
        }

        self::assertSame(
            5000,
            $result->getPersistedCount(),
        );

        self::assertSame(
            5000,
            $result->getFailedCount(),
        );

        /**
         * Protection mémoire active.
         */
        self::assertCount(
            PersistenceLimits::MAX_ERRORS,
            $result->getErrors(),
        );
    }

    public function testItHandlesIntegerOverflow(): void
    {
        $result = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertSame(
            PHP_INT_MAX,
            $result->getTotalCount(),
        );
    }

    public function testItHandlesMergeIntegerOverflow(): void
    {
        $first = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: 0,
        );

        $second = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: 0,
        );

        $merged = $first->merge($second);

        self::assertSame(
            PHP_INT_MAX,
            $merged->getPersistedCount(),
        );
    }

    public function testItHandlesEmptyMerge(): void
    {
        $first = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        $second = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        $merged = $first->merge($second);

        self::assertSame(
            0,
            $merged->getTotalCount(),
        );

        self::assertFalse(
            $merged->hasErrors(),
        );
    }

    public function testItHandlesNanSuccessRateProtection(): void
    {
        $result = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        self::assertFalse(
            is_nan(
                $result->getSuccessRate(),
            ),
        );
    }

    public function testItHandlesLargeErrorDeduplication(): void
    {
        $errors = [];

        for ($i = 0; $i < 1000; ++$i) {
            $errors[] = 'duplicate';
        }

        $result = PersistenceResult::failure(
            failedCount: 1000,
            errors: $errors,
        );

        self::assertSame(
            [
                'duplicate',
            ],
            $result->getErrors(),
        );
    }

    public function testItTrimsWhitespaceAroundErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                '   hello   ',
            ],
        );

        self::assertSame(
            [
                'hello',
            ],
            $result->getErrors(),
        );
    }

    public function testItHandlesHugeUnicodeErrors(): void
    {
        $payload = str_repeat(
            '🔥',
            10000,
        );

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $payload,
            ],
        );

        self::assertLessThanOrEqual(
            PersistenceLimits::MAX_ERROR_LENGTH,
            mb_strlen(
                $result->getErrors()[0],
            ),
        );
    }

    public function testItReturnsImmutableErrors(): void
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

    public function testItHandlesMassiveErrorPayloadWithoutMemoryExplosion(): void
    {
        $errors = [];

        for ($i = 0; $i < 50000; ++$i) {
            $errors[] = str_repeat(
                'x',
                1000,
            );
        }

        $result = PersistenceResult::failure(
            failedCount: 50000,
            errors: $errors,
        );

        self::assertCount(
            1,
            $result->getErrors(),
        );
    }
}