<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Infrastructure\Doctrine;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use App\Persistence\Constantes\PersistenceLimits;
use App\Persistence\Infrastructure\Doctrine\DoctrineLogWriter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Crash tests robustesse
 * du DoctrineLogWriter.
 */
final class DoctrineLogWriterCrashTest extends TestCase
{
    /**
     * But : Vérifier qu'un lot massif est strictement borné avant insertion SQL.
     *
     * Entrée : 10 000 LogEntry valides.
     * Résultat attendu : insert() appelé 500 fois max, résultat success stable.
     */
    public function testPersistSurvivesMassiveBatchByApplyingConfiguredLimit(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('beginTransaction');

        $connection
            ->expects(self::once())
            ->method('commit');

        $connection
            ->expects(self::exactly(PersistenceLimits::MAX_PERSIST_BATCH_SIZE))
            ->method('insert')
            ->willReturn(1);

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $entries = [];

        for ($i = 0; $i < 10000; ++$i) {
            $entries[] = $this->createLogEntry();
        }

        $result = $writer->persist($entries);

        self::assertTrue($result->isSuccess());

        self::assertSame(
            PersistenceLimits::MAX_PERSIST_BATCH_SIZE,
            $result->getPersistedCount(),
        );
    }

    /**
     * But : Vérifier que persist() retourne isFailure() si beginTransaction() crashe.
     *
     * Entrée : beginTransaction() lève \RuntimeException, isTransactionActive()=false
     * Résultat attendu : isFailure()=true, failedCount=1, hasErrors()=true
     */
    public function testPersistSurvivesTransactionCrash(): void
    {
        $connection = $this->createStub(
            Connection::class,
        );

        $connection
            ->method('beginTransaction')
            ->willThrowException(
                new \RuntimeException(
                    'Transaction crash',
                ),
            );

        $connection
            ->method('isTransactionActive')
            ->willReturn(false);

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
        ]);

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertSame(
            1,
            $result->getFailedCount(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );
    }

    /**
     * But : Vérifier que persist() retourne isFailure() même si rollBack() crashe aussi.
     *
     * Entrée : beginTransaction() lève exception, rollBack() lève aussi exception
     * Résultat attendu : isFailure()=true, failedCount=1
     */
    public function testPersistSurvivesRollbackFailure(): void
    {
        $connection = $this->createStub(
            Connection::class,
        );

        $connection
            ->method('beginTransaction')
            ->willThrowException(
                new \RuntimeException(
                    'Begin transaction failure',
                ),
            );

        $connection
            ->method('isTransactionActive')
            ->willReturn(true);

        $connection
            ->method('rollBack')
            ->willThrowException(
                new \RuntimeException(
                    'Rollback failure',
                ),
            );

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
        ]);

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertSame(
            1,
            $result->getFailedCount(),
        );
    }

    /**
     * But : Vérifier qu'un échec de commit déclenche rollback et retourne une erreur technique explicite.
     *
     * Entrée : insert OK puis commit() lève une RuntimeException SQLSTATE.
     * Résultat attendu : rollBack() appelé, isFailure()=true, message d'erreur technique présent.
     */
    public function testPersistRollsBackWhenCommitFailsAndReturnsExplicitTechnicalError(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('beginTransaction');

        $connection
            ->expects(self::once())
            ->method('insert')
            ->willReturn(1);

        $connection
            ->expects(self::once())
            ->method('commit')
            ->willThrowException(
                new \RuntimeException(
                    'SQLSTATE[HY000] [2002] Connection refused',
                ),
            );

        $connection
            ->expects(self::once())
            ->method('isTransactionActive')
            ->willReturn(true);

        $connection
            ->expects(self::once())
            ->method('rollBack');

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
        ]);

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertSame(
            1,
            $result->getFailedCount(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );

        $firstError = $result->getErrors()[0] ?? '';

        self::assertStringContainsString(
            'Persistence batch failure:',
            $firstError,
        );

        self::assertStringContainsString(
            'SQLSTATE[HY000] [2002] Connection refused',
            $firstError,
        );
    }

    /**
     * But : Vérifier que persist() retourne isFailure() avec 100 entrées si tous les inserts échouent.
     *
     * Entrée : 100 LogEntry, insert() lance \RuntimeException à chaque fois
     * Résultat attendu : isFailure()=true, failedCount=100, count(getErrors())=100
     */
    public function testPersistSurvivesMassiveInsertFailures(): void
    {
        $connection = $this->createStub(
            Connection::class,
        );

        $connection
            ->method('beginTransaction');

        $connection
            ->method('commit');

        $connection
            ->method('insert')
            ->willThrowException(
                new \RuntimeException(
                    'Disk full',
                ),
            );

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $entries = [];

        for ($i = 0; $i < 100; ++$i) {
            $entries[] = $this->createLogEntry();
        }

        $result = $writer->persist(
            $entries,
        );

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertSame(
            100,
            $result->getFailedCount(),
        );

        self::assertCount(
            100,
            $result->getErrors(),
        );

        $firstError = $result->getErrors()[0] ?? '';

        self::assertStringContainsString(
            'Disk full',
            $firstError,
        );
    }

    private function createLogEntry(): LogEntry
    {
        return new LogEntry(
            message: 'Critical error',
            level: LogLevel::CRITICAL,
            domain: 'crash.example.com',
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),
            client: new Client('worker'),
            request: new Request(
                method: 'POST',
                uri: new Uri('/crash'),
                userAgent: 'PHPUnit',
            ),
            ipAddress: new IpAddress(
                '127.0.0.1',
            ),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
            requestId: new RequestId(
                'req_crash',
            ),
        );
    }
}