<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use App\Persistence\Application\PersistLogBatchHandler;
use App\Persistence\Application\PersistLogBatchRequest;
use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de PersistLogBatchHandler.
 */
final class PersistLogBatchHandlerTest extends TestCase
{
    public function testItPersistsBatch(): void
    {
        $entries = [
            $this->createEntry(),
            $this->createEntry(),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with($entries)
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 2,
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

    public function testItDoesNotPersistEmptyBatch(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::never())
            ->method('persist');

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([]),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );
    }

    public function testItReturnsFailureWhenWriterFails(): void
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
                        'sql error',
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

    public function testItAlwaysReturnsPersistenceResult(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::never())
            ->method('persist');

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([]),
        );

        self::assertInstanceOf(
            PersistenceResult::class,
            $result,
        );
    }

    public function testItPreservesEntriesOrder(): void
    {
        $entries = [
            $this->createEntry(
                message: 'first',
            ),
            $this->createEntry(
                message: 'second',
            ),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with(
                self::callback(
                    static function (
                        array $entries,
                    ): bool {
                        return $entries[0]->message()
                                === 'first'
                            && $entries[1]->message()
                                === 'second';
                    },
                ),
            )
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 2,
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

    public function testItHandlesSingleEntry(): void
    {
        $entries = [
            $this->createEntry(),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with($entries)
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 1,
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

    private function createEntry(
        string $message = 'Payment failed',
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: LogLevel::ERROR,
            domain: 'billing',
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),
            client: new Client('checkout-app'),
            request: new Request(
                new Uri('/orders'),
                'POST',
                'Mozilla/5.0',
            ),
            ipAddress: new IpAddress(
                '127.0.0.1',
            ),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
        );
    }
}