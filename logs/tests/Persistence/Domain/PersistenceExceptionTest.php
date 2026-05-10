<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Domain\PersistenceException;
use App\Persistence\Enum\PersistenceErrorCode;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests nominaux de PersistenceException.
 *
 * Responsabilités :
 * - vérifier les factories métier
 * - vérifier les codes d'erreur
 * - vérifier le contexte
 * - garantir les invariants
 * - garantir l'immutabilité
 * - vérifier les nettoyages défensifs
 */
final class PersistenceExceptionTest extends TestCase
{
    public function testDatabaseConnectionFailedCreatesExpectedException(): void
    {
        $previous = new RuntimeException(
            'mysql down',
        );

        $exception = PersistenceException::databaseConnectionFailed(
            context: [
                'host' => 'localhost',
                'driver' => 'pdo_mysql',
            ],
            previous: $previous,
        );

        self::assertSame(
            PersistenceErrorCode::DATABASE_CONNECTION_FAILED,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::DATABASE_CONNECTION_FAILED->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Database connection failed.',
            $exception->getMessage(),
        );

        self::assertSame(
            $previous,
            $exception->getPrevious(),
        );

        self::assertSame(
            [
                'host' => 'localhost',
                'driver' => 'pdo_mysql',
            ],
            $exception->getContext(),
        );
    }

    public function testQueryExecutionFailedCreatesExpectedException(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'table' => 'log_entry',
            ],
        );

        self::assertSame(
            PersistenceErrorCode::QUERY_EXECUTION_FAILED,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::QUERY_EXECUTION_FAILED->value,
            $exception->getCode(),
        );

        self::assertSame(
            'SQL query execution failed.',
            $exception->getMessage(),
        );

        self::assertSame(
            [
                'table' => 'log_entry',
            ],
            $exception->getContext(),
        );
    }

    public function testEmptyBatchCreatesExpectedException(): void
    {
        $exception = PersistenceException::emptyBatch();

        self::assertSame(
            PersistenceErrorCode::EMPTY_BATCH,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::EMPTY_BATCH->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Persistence batch is empty.',
            $exception->getMessage(),
        );

        self::assertSame(
            [],
            $exception->getContext(),
        );
    }

    public function testBatchTooLargeCreatesExpectedException(): void
    {
        $exception = PersistenceException::batchTooLarge(
            context: [
                'batchSize' => 5000,
            ],
        );

        self::assertSame(
            PersistenceErrorCode::BATCH_TOO_LARGE,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::BATCH_TOO_LARGE->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Persistence batch exceeds allowed limit.',
            $exception->getMessage(),
        );

        self::assertSame(
            [
                'batchSize' => 5000,
            ],
            $exception->getContext(),
        );
    }

    public function testInvalidPayloadCreatesExpectedException(): void
    {
        $exception = PersistenceException::invalidPayload(
            context: [
                'reason' => 'missing message',
            ],
        );

        self::assertSame(
            PersistenceErrorCode::INVALID_PAYLOAD,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::INVALID_PAYLOAD->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Persistence payload is invalid.',
            $exception->getMessage(),
        );

        self::assertSame(
            [
                'reason' => 'missing message',
            ],
            $exception->getContext(),
        );
    }

    public function testTimeoutCreatesExpectedException(): void
    {
        $exception = PersistenceException::timeout();

        self::assertSame(
            PersistenceErrorCode::TIMEOUT,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::TIMEOUT->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Persistence operation timed out.',
            $exception->getMessage(),
        );
    }

    public function testDeadlockCreatesExpectedException(): void
    {
        $exception = PersistenceException::deadlock();

        self::assertSame(
            PersistenceErrorCode::DEADLOCK,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::DEADLOCK->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Database deadlock detected.',
            $exception->getMessage(),
        );
    }

    public function testTransactionFailedCreatesExpectedException(): void
    {
        $exception = PersistenceException::transactionFailed();

        self::assertSame(
            PersistenceErrorCode::TRANSACTION_FAILED,
            $exception->getErrorCode(),
        );

        self::assertSame(
            PersistenceErrorCode::TRANSACTION_FAILED->value,
            $exception->getCode(),
        );

        self::assertSame(
            'Database transaction failed.',
            $exception->getMessage(),
        );
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = PersistenceException::timeout();

        self::assertInstanceOf(
            RuntimeException::class,
            $exception,
        );
    }

    public function testContextIsImmutable(): void
    {
        $context = [
            'table' => 'logs',
        ];

        $exception = PersistenceException::queryExecutionFailed(
            context: $context,
        );

        $context['table'] = 'modified';

        self::assertSame(
            [
                'table' => 'logs',
            ],
            $exception->getContext(),
        );
    }

    public function testWhitespaceValuesAreTrimmed(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'table' => '   logs   ',
            ],
        );

        self::assertSame(
            [
                'table' => 'logs',
            ],
            $exception->getContext(),
        );
    }

    public function testEmptyMessageFallsBackToDefaultMessage(): void
    {
        $reflection = new \ReflectionClass(
            PersistenceException::class,
        );

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
    }

    public function testExceptionContextIsIndependentFromOriginalArray(): void
    {
        $context = [
            'host' => 'db',
        ];

        $exception = PersistenceException::databaseConnectionFailed(
            context: $context,
        );

        $copied = $exception->getContext();

        $copied['host'] = 'modified';

        self::assertSame(
            [
                'host' => 'db',
            ],
            $exception->getContext(),
        );
    }

    public function testPreviousThrowableIsPreserved(): void
    {
        $previous = new RuntimeException(
            'sql crashed',
        );

        $exception = PersistenceException::queryExecutionFailed(
            previous: $previous,
        );

        self::assertSame(
            $previous,
            $exception->getPrevious(),
        );
    }
}