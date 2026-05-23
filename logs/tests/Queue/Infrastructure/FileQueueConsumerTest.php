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
            ->expects(self::once())
            ->method('persist')
            ->willThrowException(new \RuntimeException('db down'));

        $reader
            ->expects(self::never())
            ->method('delete');

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $result = (new FileQueueConsumer($reader, $persistence))->consume();

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(1, $result->getMovedToFailedCount());
    }
}
