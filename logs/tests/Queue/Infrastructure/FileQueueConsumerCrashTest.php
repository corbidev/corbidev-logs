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
 * - garantir le retry borné
 * - éviter les boucles infinies
 * - protéger le batch sous charge
 * - isoler les échecs item par item
 */
final class FileQueueConsumerCrashTest extends TestCase
{
    /**
     * But : Vérifier que 100 items en échec permanent ne provoquent pas de boucle infinie.
     *
     * Entrée : 100 items, persist() échoue toujours, maxRetries=3.
     * Résultat attendu : 300 tentatives, 100 échecs, 100 moves failed.
     */
    public function test_it_survives_massive_persistence_failures_with_bounded_retries(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $items = [];

        for ($i = 0; $i < 100; ++$i) {
            $items[] = [
                'path' => __FILE__,
                'payload' => ['message' => 'fail-' . $i],
            ];
        }

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn($items);

        $persistence
            ->expects(self::exactly(300))
            ->method('persist')
            ->willThrowException(new \RuntimeException('db down'));

        $reader
            ->expects(self::never())
            ->method('delete');

        $reader
            ->expects(self::exactly(100))
            ->method('moveToFailed')
            ->with(__FILE__);

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume(100);

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(100, $result->getFailedCount());
        self::assertSame(100, $result->getMovedToFailedCount());
        self::assertSame(200, $result->getRetryCount());
    }

    /**
     * But : Vérifier qu'un payload invalide ne provoque pas d'appel persistence ni de crash batch.
     *
     * Entrée : item avec payload non tableau.
     * Résultat attendu : 0 appel persist(), item en échec, move failed exécuté.
     */
    public function test_it_handles_invalid_payload_without_calling_persistence(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => __FILE__,
            'payload' => 'invalid',
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $persistence
            ->expects(self::never())
            ->method('persist');

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
        self::assertSame(0, $result->getRetryCount());
    }

    /**
     * But : Vérifier qu'un path invalide isole l'échec sans appel persistence.
     *
     * Entrée : item avec path null.
     * Résultat attendu : 0 appel persist(), échec compté, pas de move failed possible.
     */
    public function test_it_handles_invalid_path_without_secondary_crash(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $item = [
            'path' => null,
            'payload' => ['message' => 'bad-path'],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([$item]);

        $persistence
            ->expects(self::never())
            ->method('persist');

        $reader
            ->expects(self::never())
            ->method('delete');

        $reader
            ->expects(self::never())
            ->method('moveToFailed');

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume();

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(0, $result->getMovedToFailedCount());
        self::assertSame(0, $result->getRetryCount());
    }

    /**
     * But : Vérifier que le batch continue après un item en échec retry puis un succès.
     *
     * Entrée : 2 items (1er échoue 3 fois, 2e réussit immédiatement).
     * Résultat attendu : 4 appels persist(), 1 failed, 1 processed.
     */
    public function test_it_continues_batch_after_retry_exhaustion_on_first_item(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $items = [
            [
                'path' => __FILE__,
                'payload' => ['message' => 'first-fails'],
            ],
            [
                'path' => '/processing/second-ok.json',
                'payload' => ['message' => 'second-ok'],
            ],
        ];

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn($items);

        $firstItemAttempts = 0;

        $persistence
            ->expects(self::exactly(4))
            ->method('persist')
            ->willReturnCallback(
                static function (array $payload) use (&$firstItemAttempts): void {
                    if (($payload['message'] ?? null) !== 'first-fails') {
                        return;
                    }

                    ++$firstItemAttempts;

                    throw new \RuntimeException('first item failure');
                },
            );

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $reader
            ->expects(self::once())
            ->method('delete')
            ->with('/processing/second-ok.json');

        $result = (new FileQueueConsumer($reader, $persistence, 3))->consume();

        self::assertSame(1, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(1, $result->getMovedToFailedCount());
        self::assertSame(2, $result->getRetryCount());
    }

    /**
     * But : Vérifier la robustesse avec une valeur de retry élevée mais bornée.
     *
     * Entrée : maxRetries=20, un item toujours en échec.
     * Résultat attendu : 20 tentatives exactement puis échec contrôlé.
     */
    public function test_it_respects_high_but_bounded_retry_limit(): void
    {
        $reader = $this->createMock(QueueReaderInterface::class);
        $persistence = $this->createMock(QueuePersistenceInterface::class);

        $reader
            ->expects(self::once())
            ->method('readBatch')
            ->with(100)
            ->willReturn([
                [
                    'path' => __FILE__,
                    'payload' => ['message' => 'always-fails'],
                ],
            ]);

        $persistence
            ->expects(self::exactly(20))
            ->method('persist')
            ->willThrowException(new \RuntimeException('still failing'));

        $reader
            ->expects(self::once())
            ->method('moveToFailed')
            ->with(__FILE__);

        $reader
            ->expects(self::never())
            ->method('delete');

        $result = (new FileQueueConsumer($reader, $persistence, 20))->consume();

        self::assertSame(0, $result->getProcessedCount());
        self::assertSame(1, $result->getFailedCount());
        self::assertSame(1, $result->getMovedToFailedCount());
        self::assertSame(19, $result->getRetryCount());
    }
}
