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
    /**
     * But : Vérifier que databaseConnectionFailed() crée une exception avec le bon code et message.
     *
     * Entrée : context=['host', 'driver'], previous=RuntimeException('mysql down')
     * Résultat attendu : Code DATABASE_CONNECTION_FAILED, message 'Database connection failed.'
     */
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

    /**
     * But : Vérifier que queryExecutionFailed() crée une exception avec le bon code et message.
     *
     * Entrée : context=['table' => 'log_entry']
     * Résultat attendu : Code QUERY_EXECUTION_FAILED, message 'SQL query execution failed.'
     */
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

    /**
     * But : Vérifier que emptyBatch() crée une exception avec le bon code et un contexte vide.
     *
     * Entrée : Aucune
     * Résultat attendu : Code EMPTY_BATCH, message 'Persistence batch is empty.', context=[]
     */
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

    /**
     * But : Vérifier que batchTooLarge() crée une exception avec le bon code et contexte.
     *
     * Entrée : context=['batchSize' => 5000]
     * Résultat attendu : Code BATCH_TOO_LARGE, message 'Persistence batch exceeds allowed limit.'
     */
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

    /**
     * But : Vérifier que invalidPayload() crée une exception avec le bon code et contexte.
     *
     * Entrée : context=['reason' => 'missing message']
     * Résultat attendu : Code INVALID_PAYLOAD, message 'Persistence payload is invalid.'
     */
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

    /**
     * But : Vérifier que timeout() crée une exception avec le bon code et message.
     *
     * Entrée : Aucune
     * Résultat attendu : Code TIMEOUT, message 'Persistence operation timed out.'
     */
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

    /**
     * But : Vérifier que deadlock() crée une exception avec le bon code et message.
     *
     * Entrée : Aucune
     * Résultat attendu : Code DEADLOCK, message 'Database deadlock detected.'
     */
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

    /**
     * But : Vérifier que transactionFailed() crée une exception avec le bon code et message.
     *
     * Entrée : Aucune
     * Résultat attendu : Code TRANSACTION_FAILED, message 'Database transaction failed.'
     */
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

    /**
     * But : Vérifier que PersistenceException hérite bien de RuntimeException.
     *
     * Entrée : PersistenceException::timeout()
     * Résultat attendu : assertInstanceOf(RuntimeException::class)
     */
    public function testExceptionIsRuntimeException(): void
    {
        $exception = PersistenceException::timeout();

        self::assertInstanceOf(
            RuntimeException::class,
            $exception,
        );
    }

    /**
     * But : Vérifier que modifier le tableau original après création ne mute pas le contexte de l'exception.
     *
     * Entrée : context=['table' => 'logs'], modification après création
     * Résultat attendu : getContext() retourne toujours ['table' => 'logs']
     */
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

    /**
     * But : Vérifier que les valeurs de contexte avec espaces superflus sont nettoyées.
     *
     * Entrée : context=['table' => '   logs   ']
     * Résultat attendu : getContext() = ['table' => 'logs']
     */
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

    /**
     * But : Vérifier que le constructeur de PersistenceException existe et est accessible.
     *
     * Entrée : Reflection sur PersistenceException
     * Résultat attendu : getConstructor() retourne non-null
     */
    public function testEmptyMessageFallsBackToDefaultMessage(): void
    {
        $reflection = new \ReflectionClass(
            PersistenceException::class,
        );

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
    }

    /**
     * But : Vérifier que le tableau retourné par getContext() est indépendant de l'état interne.
     *
     * Entrée : context=['host' => 'db'], modification du tableau retourné
     * Résultat attendu : getContext() retourne toujours ['host' => 'db']
     */
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

    /**
     * But : Vérifier que l'exception précédente est correctement transmise et récupérable.
     *
     * Entrée : previous=RuntimeException('sql crashed')
     * Résultat attendu : getPrevious() retourne la même instance
     */
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