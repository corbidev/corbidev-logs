<?php

declare(strict_types=1);

namespace App\Log\Application\Ingestion;

use App\Log\Application\Factory\LogEntryFactoryInterface;
use App\Log\Application\Normalizer\LogPayloadNormalizer;
use App\Queue\Application\QueueWriterInterface;

/**
 * Orchestre le flux d'ingestion write side.
 *
 * Responsabilités :
 * - normaliser chaque log entrant
 * - créer un LogEntry robuste
 * - écrire le résultat dans la queue disque
 */
final readonly class LogIngestionPipeline
{
    public function __construct(
        private LogPayloadNormalizer $normalizer,
        private LogEntryFactoryInterface $logEntryFactory,
        private QueueWriterInterface $queueWriter,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $logs
     *
     * @return array{queued:int,files:list<string>}
     */
    public function process(array $logs): array
    {
        $files = [];

        foreach ($logs as $log) {
            $normalized = $this->normalizer->normalize($log);

            $entry = $this->logEntryFactory->create($normalized);

            $files[] = $this->queueWriter->write($entry->toArray());
        }

        return [
            'queued' => count($files),
            'files' => $files,
        ];
    }
}
