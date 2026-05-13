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