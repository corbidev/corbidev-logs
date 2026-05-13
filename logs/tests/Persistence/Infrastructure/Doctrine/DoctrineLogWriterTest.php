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
 * Tests fonctionnels robustes
 * du DoctrineLogWriter.
 */
final class DoctrineLogWriterTest extends TestCase
{
    /**
     * But : Vérifier que persist([]) retourne un résultat "nothingToPersist".
     *
     * Entrée : Tableau vide
     * Résultat attendu : isNothingToPersist()=true, persistedCount=0, failedCount=0
     */
    public function testPersistReturnsNothingToPersistWhenBatchIsEmpty(): void
    {
        $connection = $this->createStub(
            Connection::class,
        );

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([]);

        self::assertTrue(
            $result->isNothingToPersist(),
        );

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );
    }

    /**
     * But : Vérifier que persist() déclenche beginTransaction, commit, insert et retourne success.
     *
     * Entrée : Une seule LogEntry
     * Résultat attendu : isSuccess()=true, persistedCount=1, failedCount=0
     */
    public function testPersistReturnsSuccess(): void
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
            ->expects(self::once())
            ->method('insert');

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
        ]);

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->hasFailures(),
        );

        self::assertSame(
            1,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );
    }

    /**
     * But : Vérifier que persist() retourne un résultat partiel si un insert sur deux échoue.
     *
     * Entrée : Deux LogEntry, le 2e insert lance \RuntimeException
     * Résultat attendu : isPartial()=true, persistedCount=1, failedCount=1, hasErrors()=true
     */
    public function testPersistReturnsPartialWhenOneInsertFails(): void
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
            ->expects(self::exactly(2))
            ->method('insert')
            ->willReturnCallback(
                static function (
                    string $_table,
                    array $data,
                ): int {
                    if (($data['request_id'] ?? '') === 'req_test_fail') {
                        throw new \RuntimeException(
                            'SQL insert failed',
                        );
                    }

                    return 1;
                },
            );

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
            $this->createLogEntry('req_test_fail'),
        ]);

        self::assertTrue(
            $result->isPartial(),
        );

        self::assertSame(
            1,
            $result->getPersistedCount(),
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
     * But : Vérifier que persist() retourne isFailure() si tous les inserts échouent.
     *
     * Entrée : Deux LogEntry, tous les inserts lancent \RuntimeException
     * Résultat attendu : isFailure()=true, persistedCount=0, failedCount=2, hasErrors()=true
     */
    public function testPersistReturnsFailureWhenAllInsertionsFail(): void
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
            ->expects(self::exactly(2))
            ->method('insert')
            ->willThrowException(
                new \RuntimeException(
                    'Database unavailable',
                ),
            );

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist([
            $this->createLogEntry(),
            $this->createLogEntry(),
        ]);

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            2,
            $result->getFailedCount(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );
    }

    /**
     * But : Vérifier que persist() tronque un batch de 700 entrées à 500 (limite max).
     *
     * Entrée : 700 LogEntry
     * Résultat attendu : insert appelé 500 fois, isSuccess()=true, persistedCount=500
     */
    public function testPersistTruncatesOversizedBatch(): void
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
            ->expects(self::exactly(500))
            ->method('insert');

        $entries = [];

        for ($i = 0; $i < 700; ++$i) {
            $entries[] = $this->createLogEntry();
        }

        $writer = new DoctrineLogWriter(
            $connection,
            new NullLogger(),
        );

        $result = $writer->persist(
            $entries,
        );

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertSame(
            500,
            $result->getPersistedCount(),
        );
    }

    private function createLogEntry(
        string $requestId = 'req_test_123',
    ): LogEntry {
        return new LogEntry(
            message: 'Login failed',
            level: LogLevel::ERROR,
            domain: 'app.example.com',
            environment: Environment::Production,
            httpStatus: new HttpStatus(401),
            client: new Client('web'),
            request: new Request(
                method: 'POST',
                uri: new Uri('/login'),
                userAgent: 'PHPUnit',
            ),
            ipAddress: new IpAddress(
                '127.0.0.1',
            ),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
            requestId: new RequestId(
                $requestId,
            ),
            context: [
                'security' => [
                    'firewall' => 'main',
                ],
            ],
            extra: [
                'memoryMb' => 12.4,
            ],
        );
    }
}