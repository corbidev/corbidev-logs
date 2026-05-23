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
 * Tests unitaires de FileQueueConsumer.
 */
final class FileQueueConsumerTest extends TestCase
{
    /**
     * But : Vérifier que le fichier est supprimé uniquement après persistence réussie.
     *
     * Entrée : un item valide avec persistence sans exception.
     * Résultat attendu : delete() appelé une fois, moveToFailed() jamais.
     */
    public function test_it_deletes_item_only_after_successful_persistence(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => '/processing/success.json',
            'payload' => ['message' => 'ok'],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $persistence
            ->expects(self::once())
            ->method('persist')
            ->with(['message' => 'ok']);

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/success.json');

        $reader
            ->expects(self::never())
            ->method('moveToFailed');

        $result = (new FileQueueConsumer($reader, $persistence))->consume();

        self::assertSame(1, $result->getProcessedCount());
        self::assertSame(0, $result->getFailedCount());
    }

    /**
     * But : Vérifier qu'un échec de persistence n'entraîne pas de suppression.
     *
     * Entrée : persistence qui lève une RuntimeException.
     * Résultat attendu : moveToFailed() appelé et delete() non appelé.
     */
    public function test_it_moves_item_to_failed_when_persistence_throws(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => __FILE__,
            'payload' => ['message' => 'ko'],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $persistence
            ->expects(self::exactly(3))
            ->method('persist')
            ->willThrowException(new \RuntimeException('db down'));

        $reader
            ->expects(self::never())
            ->method('delete');

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume();

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(1, $result->getMovedToFailedCount());
    }

    /**
     * But : Vérifier que la persistence est retentée de façon bornée avant succès.
     *
     * Entrée : persist() échoue 2 fois puis réussit au 3e essai.
     * Résultat attendu : item traité, delete appelé, aucun moveToFailed.
     */
    public function test_it_retries_and_succeeds_before_reaching_retry_limit(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => '/processing/retry-success.json',
            'payload' => ['message' => 'retry-success'],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $callCount = 0;

        $persistence
            ->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(
                static function () use (&$callCount): void {
                    ++$callCount;

                    if ($callCount < 3) {
                        throw new \RuntimeException('transient failure');
                    }
                },
            );

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/retry-success.json');

        $reader
            ->expects(self::never())
            ->method('moveToFailed');

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume();

        self::assertSame(1, $result->getProcessedCount());
        self::assertSame(0, $result->getFailedCount());
        self::assertSame(0, $result->getMovedToFailedCount());
    }

    /**
     * But : Vérifier que le nombre de retries est borné et déterministe.
     *
     * Entrée : persist() échoue systématiquement avec maxRetries=3.
     * Résultat attendu : 3 tentatives, puis moveToFailed et échec item.
     */
    public function test_it_stops_after_configured_retry_limit(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => __FILE__,
            'payload' => ['message' => 'retry-failed'],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $persistence
            ->expects(self::exactly(3))
            ->method('persist')
            ->willThrowException(new \RuntimeException('persistent failure'));

        $reader
            ->expects(self::never())
            ->method('delete');

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume();

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(1, $result->getMovedToFailedCount());
    }

    /**
     * But : Vérifier qu'une borne de retry invalide est refusée explicitement.
     *
     * Entrée : maxRetries=0.
     * Résultat attendu : InvalidArgumentException levée au constructeur.
     */
    public function test_it_rejects_non_positive_retry_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FileQueueConsumer(
            $this->createMock(QueueReaderInterface::class),
            $this->createMock(QueuePersistenceInterface::class),
            0,
        );
    }
}
