<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

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
use App\Persistence\Application\PersistLogBatchHandler;
use App\Persistence\Application\PersistLogBatchRequest;
use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;
use Error;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * Crash tests critiques de PersistLogBatchHandler.
 *
 * Objectifs :
 * - garantir robustesse
 * - garantir absence de crash
 * - garantir stabilité batch
 * - garantir capture des erreurs
 */
final class PersistLogBatchHandlerCrashTest extends TestCase
{
    /**
     * But : Vérifier que le handler capture une RuntimeException lancée par le writer.
     *
     * Entrée : Writer qui lance RuntimeException('DB connection lost')
     * Résultat attendu : result.isSuccess() = false, aucune exception propagée
     */
    public function testItSurvivesRuntimeException(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willThrowException(
                new RuntimeException(
                    'db failure',
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([
                $this->createEntry(),
            ]),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler capture une Error lancée par le writer.
     *
     * Entrée : Writer qui lance Error('fatal')
     * Résultat attendu : result.isSuccess() = false, aucune exception propagée
     */
    public function testItSurvivesError(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willThrowException(
                new Error('fatal'),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([
                $this->createEntry(),
            ]),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler traite un lot de 10 000 entrées sans crash.
     *
     * Entrée : PersistLogBatchRequest avec 10 000 LogEntry
     * Résultat attendu : result.isSuccess() = true
     */
    public function testItSurvivesMassiveBatch(): void
    {
        $entries = [];

        for ($i = 0; $i < 10000; ++$i) {
            $entries[] = $this->createEntry(
                requestId: 'req_batch_' . $i,
            );
        }

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: count(
                        $entries,
                    ),
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                $entries,
            ),
        );

        self::assertTrue(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler retourne failure quand le writer retourne PersistenceResult::failure.
     *
     * Entrée : Writer retournant PersistenceResult::failure()
     * Résultat attendu : result.isSuccess() = false
     */
    public function testItSurvivesWriterReturningFailure(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willReturn(
                PersistenceResult::failure(
                    failedCount: 1,
                    errors: [
                        'sql failure',
                    ],
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([
                $this->createEntry(),
            ]),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler gère 1 000 appels consécutifs avec des lots vides.
     *
     * Entrée : 1 000 PersistLogBatchRequest vides
     * Résultat attendu : PersistenceResult retourné à chaque appel, aucune exception
     */
    public function testItSurvivesEmptyRequestRepeatedly(): void
    {
        $writer = $this->createStub(
            LogWriterInterface::class,
        );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        for ($i = 0; $i < 1000; ++$i) {
            $result = $handler->handle(
                new PersistLogBatchRequest(
                    [],
                ),
            );

            self::assertInstanceOf(
                PersistenceResult::class,
                $result,
            );
        }
    }

    /**
     * But : Vérifier que le handler résiste à 100 échecs consécutifs du writer.
     *
     * Entrée : 100 appels avec writer lançant RuntimeException à chaque fois
     * Résultat attendu : result.isSuccess() = false à chaque appel, aucune exception propagée
     */
    public function testItSurvivesMultipleFailures(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::exactly(100))
            ->method('persist')
            ->willThrowException(
                new RuntimeException(
                    'database unavailable',
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        for ($i = 0; $i < 100; ++$i) {
            $result = $handler->handle(
                new PersistLogBatchRequest([
                    $this->createEntry(
                        requestId: 'req_failure_' . $i,
                    ),
                ]),
            );

            self::assertFalse(
                $result->isSuccess(),
            );
        }
    }

    private function createEntry(
        string $requestId = 'req_checkout_test',
    ): LogEntry {
        return new LogEntry(
            message: 'Payment failed',

            level: LogLevel::ERROR,

            domain: 'billing',

            environment: Environment::Production,

            httpStatus: new HttpStatus(
                500,
            ),

            client: new Client(
                'checkout-app',
            ),

            request: new Request(
                uri: new Uri(
                    '/orders',
                ),

                method: 'POST',

                userAgent: 'Mozilla/5.0',
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
        );
    }
}