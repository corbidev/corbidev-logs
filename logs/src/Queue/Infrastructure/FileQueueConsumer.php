<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Queue\Application\QueueConsumeResult;
use App\Queue\Application\QueueConsumerInterface;
use App\Queue\Application\QueuePersistenceInterface;
use App\Queue\Application\QueueReaderInterface;

/**
 * Consumer de queue disque.
 *
 * Responsabilités :
 * - orchestrer la consommation batch
 * - coordonner lecture et persistence
 * - supprimer les fichiers persistés
 * - déplacer les fichiers en failed
 * - isoler les erreurs de persistence
 */
final readonly class FileQueueConsumer implements QueueConsumerInterface
{
    public function __construct(
        private QueueReaderInterface $reader,
        private QueuePersistenceInterface $persistence,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function consume(
        int $limit = 100,
    ): QueueConsumeResult {
        $this->validateLimit($limit);

        $result = new QueueConsumeResult();

        foreach ($this->reader->readBatch($limit) as $item) {
            try {
                $this->consumeItem($item);
                $result->incrementProcessed();
            } catch (\Throwable $exception) {
                $result->incrementFailed();

                $movedToFailed = $this->handleFailedItem(
                    $item,
                    $exception,
                );

                if ($movedToFailed) {
                    $result->incrementMovedToFailed();
                }
            }
        }

        return $result;
    }

    /**
     * @param array{path:string,payload:array<string,mixed>} $item
     */
    private function consumeItem(array $item): void
    {
        $path = $this->extractPath($item);
        $payload = $this->extractPayload($item);

        $this->persistence->persist($payload);
        $this->reader->delete($path);
    }

    /**
     * @param array{path?:mixed,payload?:mixed} $item
     */
    private function handleFailedItem(
        array $item,
        \Throwable $exception,
    ): bool {
        try {
            $path = $this->extractPath($item);

            if (!file_exists($path)) {
                return false;
            }

            $this->reader->moveToFailed($path);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    private function extractPath(array $item): string
    {
        $path = $item['path'] ?? null;

        if (!is_string($path)) {
            throw new \RuntimeException('Queue item path is invalid.');
        }

        $path = trim($path);

        if ($path === '') {
            throw new \RuntimeException('Queue item path cannot be empty.');
        }

        return $path;
    }

    /**
     * @param array<string,mixed> $item
     *
     * @return array<string,mixed>
     */
    private function extractPayload(array $item): array
    {
        $payload = $item['payload'] ?? null;

        if (!is_array($payload)) {
            throw new \RuntimeException('Queue item payload is invalid.');
        }

        return $payload;
    }

    private function validateLimit(int $limit): void
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException(
                'Queue consume limit must be greater than zero.',
            );
        }
    }
}
