<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Log\Application\Factory\LogEntryFactoryInterface;
use App\Persistence\Application\PersistLogBatchHandler;
use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;
use App\Queue\Infrastructure\PersistLogBatchQueuePersistence;
use App\Tests\Shared\Factory\LogEntryFactory as TestLogEntryFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de PersistLogBatchQueuePersistence.
 */
final class PersistLogBatchQueuePersistenceTest extends TestCase
{
    /**
     * But : Vérifier qu'un payload queue valide est persisté sans erreur.
     *
     * Entrée : payload contenant externalId et environment.
     * Résultat attendu : mapping vers id/env puis persistence réussie.
     */
    public function test_it_persists_valid_queue_payload(): void
    {
        $factory = $this->createMock(LogEntryFactoryInterface::class);
        $writer = $this->createMock(LogWriterInterface::class);

        $factory
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(
                    static function (array $payload): bool {
                        return ($payload['id'] ?? null) === 'ext-1'
                            && ($payload['env'] ?? null) === 'test';
                    },
                ),
            )
            ->willReturn(
                TestLogEntryFactory::create(
                    id: 'ext-1',
                ),
            );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willReturn(PersistenceResult::success(1));

        $persistence = new PersistLogBatchQueuePersistence(
            $factory,
            new PersistLogBatchHandler($writer),
        );

        $persistence->persist([
            'externalId' => 'ext-1',
            'environment' => 'test',
            'message' => 'Queue payload',
        ]);

        self::assertTrue(true);
    }

    /**
     * But : Vérifier qu'un échec de persistence remonte une exception explicite.
     *
     * Entrée : writer retournant failure.
     * Résultat attendu : RuntimeException levée par l'adaptateur.
     */
    public function test_it_throws_when_batch_persistence_fails(): void
    {
        $factory = $this->createStub(LogEntryFactoryInterface::class);
        $writer = $this->createMock(LogWriterInterface::class);

        $factory
            ->method('create')
            ->willReturn(TestLogEntryFactory::create());

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willReturn(
                PersistenceResult::failure(
                    failedCount: 1,
                    errors: ['sql unavailable'],
                ),
            );

        $persistence = new PersistLogBatchQueuePersistence(
            $factory,
            new PersistLogBatchHandler($writer),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sql unavailable');

        $persistence->persist([
            'message' => 'Queue payload',
        ]);
    }
}
