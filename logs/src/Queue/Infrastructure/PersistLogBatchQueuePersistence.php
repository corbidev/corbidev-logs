<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Log\Application\Factory\LogEntryFactoryInterface;
use App\Persistence\Application\PersistLogBatchHandler;
use App\Persistence\Application\PersistLogBatchRequest;
use App\Queue\Application\QueuePersistenceInterface;

/**
 * Adaptateur Queue -> Persistence batch.
 *
 * Responsabilités :
 * - convertir un payload queue en LogEntry
 * - déléguer la persistence au handler applicatif
 * - lever une exception explicite en cas d'échec
 */
final readonly class PersistLogBatchQueuePersistence implements QueuePersistenceInterface
{
    public function __construct(
        private LogEntryFactoryInterface $logEntryFactory,
        private PersistLogBatchHandler $persistLogBatchHandler,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function persist(array $payload): void
    {
        try {
            $entry = $this->logEntryFactory->create(
                $this->normalizeQueuePayload($payload),
            );

            $result = $this->persistLogBatchHandler->handle(
                new PersistLogBatchRequest([$entry]),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Queue payload persistence failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }

        if ($result->isSuccess() && $result->getPersistedCount() > 0) {
            return;
        }

        $errors = $result->getErrors();
        $firstError = $errors[0] ?? 'unknown persistence failure';

        throw new \RuntimeException(
            sprintf(
                'Queue payload persistence failed: %s',
                $firstError,
            ),
        );
    }

    /**
     * Harmonise le payload sérialisé de la queue
     * avec les clés attendues par la factory.
     *
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>
     */
    private function normalizeQueuePayload(array $payload): array
    {
        if (!array_key_exists('id', $payload) && isset($payload['externalId'])) {
            $payload['id'] = $payload['externalId'];
        }

        if (!array_key_exists('env', $payload) && isset($payload['environment'])) {
            $payload['env'] = $payload['environment'];
        }

        return $payload;
    }
}
