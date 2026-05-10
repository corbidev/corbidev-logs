<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Application\QueuePersistenceInterface;
use App\Queue\Application\QueueReaderInterface;
use App\Queue\Infrastructure\FileQueueConsumer;
use App\Queue\Application\QueueConsumeResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests fonctionnels de FileQueueConsumer.
 */
final class FileQueueConsumerTest extends TestCase
{
    public function test_it_consumes_valid_batch(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([
                [
                    'path' => '/processing/test.json',
                    'payload' => [
                        'message' => 'test',
                    ],
                ],
            ]);

        $persistence
            ->expects(self::once())
            ->method('persist')
            ->with([
                'message' => 'test',
            ]);

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/test.json');

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertInstanceOf(
            QueueConsumeResult::class,
            $result,
        );

        self::assertSame(
            1,
            $result->getProcessedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );
    }

    public function test_it_consumes_multiple_items(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $items = [
            [
                'path' => '/processing/1.json',
                'payload' => ['message' => '1'],
            ],
            [
                'path' => '/processing/2.json',
                'payload' => ['message' => '2'],
            ],
        ];

        $reader
            ->method('readBatch')
            ->willReturn($items);

        $persistence
            ->expects(self::exactly(2))
            ->method('persist');

        $reader
            ->expects(self::exactly(2))
            ->method('delete');

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            2,
            $result->getProcessedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );
    }

    public function test_it_uses_custom_limit(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(25)
            ->willReturn([]);

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $consumer->consume(25);
    }

    public function test_it_returns_empty_result_when_queue_is_empty(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->method('readBatch')
            ->willReturn([]);

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            0,
            $result->getProcessedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );
    }

    public function test_it_moves_failed_item_to_failed_directory(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $item = [
            'path' => __FILE__,
            'payload' => [
                'message' => 'fail',
            ],
        ];

        $reader
            ->method('readBatch')
            ->willReturn([$item]);

        $persistence
            ->method('persist')
            ->willThrowException(
                new \RuntimeException('DB failure'),
            );

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            0,
            $result->getProcessedCount(),
        );

        self::assertSame(
            1,
            $result->getFailedCount(),
        );
    }

    public function test_it_deletes_only_after_successful_persistence(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $item = [
            'path' => '/processing/test.json',
            'payload' => [
                'message' => 'ok',
            ],
        ];

        $reader
            ->method('readBatch')
            ->willReturn([$item]);

        $persistence
            ->expects(self::once())
            ->method('persist');

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/test.json');

        $reader
            ->expects(self::never())
            ->method('moveToFailed');

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $consumer->consume();
    }

    public function test_it_continues_after_failed_item(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $items = [
            [
                'path' => __FILE__,
                'payload' => [
                    'message' => 'fail',
                ],
            ],
            [
                'path' => '/processing/ok.json',
                'payload' => [
                    'message' => 'ok',
                ],
            ],
        ];

        $reader
            ->method('readBatch')
            ->willReturn($items);

        $persistence
            ->method('persist')
            ->willReturnCallback(
                static function (array $payload): void {
                    if ('fail' === $payload['message']) {
                        throw new \RuntimeException(
                            'failure',
                        );
                    }
                },
            );

        $reader
            ->expects(self::once())
            ->method('moveToFailed');

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/ok.json');

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            1,
            $result->getProcessedCount(),
        );

        self::assertSame(
            1,
            $result->getFailedCount(),
        );
    }
}