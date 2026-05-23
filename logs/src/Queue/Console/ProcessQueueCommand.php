<?php

declare(strict_types=1);

namespace App\Queue\Console;

use App\Queue\Application\QueueConsumeResult;
use App\Queue\Application\QueueConsumerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande CLI de traitement de queue.
 */
#[AsCommand(
    name: 'app:queue:process',
    description: 'Process queue batch and persist logs.',
)]
final class ProcessQueueCommand extends Command
{
    private const int DEFAULT_LIMIT = 100;

    public function __construct(
        private readonly QueueConsumerInterface $consumer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'limit',
            InputArgument::OPTIONAL,
            'Maximum number of queue items to process.',
            self::DEFAULT_LIMIT,
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $limit = $this->resolveLimit($input);
            $start = microtime(true);
            $result = $this->consumer->consume($limit);
            $duration = microtime(true) - $start;

            $this->renderSuccess($io, $result, $duration);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->renderFailure($io, $exception);

            return Command::FAILURE;
        }
    }

    private function resolveLimit(InputInterface $input): int
    {
        $value = $input->getArgument('limit');

        if (is_int($value)) {
            return $this->validateLimit($value);
        }

        if (!is_string($value) || !ctype_digit($value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid queue process limit "%s".', (string) $value),
            );
        }

        return $this->validateLimit((int) $value);
    }

    private function validateLimit(int $limit): int
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException(
                'Queue process limit must be greater than zero.',
            );
        }

        return $limit;
    }

    private function renderSuccess(
        SymfonyStyle $io,
        QueueConsumeResult $result,
        float $duration,
    ): void {
        $io->success(
            sprintf('Queue process completed in %.3f seconds.', $duration),
        );

        $io->table(
            ['Metric', 'Value'],
            [
                ['Processed', (string) $result->getProcessedCount()],
                ['Failed', (string) $result->getFailedCount()],
                ['MovedToFailed', (string) $result->getMovedToFailedCount()],
                ['Duration', sprintf('%.3f sec', $duration)],
            ],
        );
    }

    private function renderFailure(
        SymfonyStyle $io,
        \Throwable $exception,
    ): void {
        $io->error([
            'Queue process failed.',
            '',
            sprintf('Type: %s', $exception::class),
            sprintf('Message: %s', $exception->getMessage()),
        ]);
    }
}
