<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Application\QueuePersistenceInterface;
use App\Queue\Application\QueueReaderInterface;
use App\Queue\Infrastructure\FileQueueConsumer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de FileQueueConsumer.
 *
 * Objectifs :
 * - garantir l'isolation des erreurs
 * - empêcher le blocage du batch
 * - sécuriser les payloads invalides
 * - garantir la robustesse orchestration
 */
final class FileQueueConsumerCrashTest extends TestCase
{
    public function test_it_rejects_invalid_limit(): void
    {
        $consumer = new FileQueueConsumer(
            $this->createMock(
                QueueReaderInterface::class,
            ),
            $this->createMock(
                QueuePersistenceInterface::class,
            ),
        );

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $consumer->consume(0);
    }

    public function test_it_handles_invalid_item_path(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->method('readBatch')
            ->willReturn([
                [
                    'path' => null,
                    'payload' => [],
                ],
            ]);

        $reader
            ->expects(self::never())
            ->method('delete');

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

    public function test_it_handles_invalid_payload(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->method('readBatch')
            ->willReturn([
                [
                    'path' => __FILE__,
                    'payload' => 'invalid',
                ],
            ]);

        $persistence
            ->expects(self::never())
            ->method('persist');

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

    public function test_it_survives_persistence_failure(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->method('readBatch')
            ->willReturn([
                [
                    'path' => __FILE__,
                    'payload' => [
                        'message' => 'fail',
                    ],
                ],
            ]);

        $persistence
            ->method('persist')
            ->willThrowException(
                new \RuntimeException(
                    'database failure',
                ),
            );

        $reader
            ->expects(self::once())
            ->method('moveToFailed');

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            1,
            $result->getFailedCount(),
        );
    }

    public function test_it_survives_move_to_failed_failure(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $reader
            ->method('readBatch')
            ->willReturn([
                [
                    'path' => __FILE__,
                    'payload' => [
                        'message' => 'fail',
                    ],
                ],
            ]);

        $persistence
            ->method('persist')
            ->willThrowException(
                new \RuntimeException(
                    'failure',
                ),
            );

        $reader
            ->method('moveToFailed')
            ->willThrowException(
                new \RuntimeException(
                    'move failure',
                ),
            );

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume();

        self::assertSame(
            1,
            $result->getFailedCount(),
        );
    }

    public function test_it_survives_massive_failures(): void
    {
        $reader = $this->createMock(
            QueueReaderInterface::class,
        );

        $persistence = $this->createMock(
            QueuePersistenceInterface::class,
        );

        $items = [];

        for ($i = 0; $i < 1000; ++$i) {
            $items[] = [
                'path' => __FILE__,
                'payload' => [
                    'message' => 'fail-' . $i,
                ],
            ];
        }

        $reader
            ->method('readBatch')
            ->willReturn($items);

        $persistence
            ->method('persist')
            ->willThrowException(
                new \RuntimeException(
                    'failure',
                ),
            );

        $consumer = new FileQueueConsumer(
            $reader,
            $persistence,
        );

        $result = $consumer->consume(1000);

        self::assertSame(
            0,
            $result->getProcessedCount(),
        );

        self::assertSame(
            1000,
            $result->getFailedCount(),
        );
    }

    public function test_it_never_stops_batch_on_single_failure(): void
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
                'path' => '/processing/success.json',
                'payload' => [
                    'message' => 'success',
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
            ->method('delete')
            ->with('/processing/success.json');

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