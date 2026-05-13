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
     * But : Vérifier que persist() retourne isFailure() si beginTransaction() crashe.
     *
     * Entrée : beginTransaction() lève \RuntimeException, isTransactionActive()=false
     * Résultat attendu : isFailure()=true, failedCount=1, hasErrors()=true
     */
    public function testPersistSurvivesTransactionCrash(): void
    {
        $connection = $this->createMock(
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
        $connection = $this->createMock(
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
     * But : Vérifier que persist() retourne isFailure() avec 100 entrées si tous les inserts échouent.
     *
     * Entrée : 100 LogEntry, insert() lance \RuntimeException à chaque fois
     * Résultat attendu : isFailure()=true, failedCount=100, count(getErrors())=100
     */
    public function testPersistSurvivesMassiveInsertFailures(): void
    {
        $connection = $this->createMock(
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
                'crash123456789',
            ),
            requestId: new RequestId(
                'req_crash',
            ),
        );
    }
}