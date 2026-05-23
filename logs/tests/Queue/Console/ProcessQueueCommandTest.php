<?php

declare(strict_types=1);

namespace App\Tests\Queue\Console;

use App\Queue\Application\QueueConsumeResult;
use App\Queue\Application\QueueConsumerInterface;
use App\Queue\Console\ProcessQueueCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * Tests de la commande app:queue:process.
 */
final class ProcessQueueCommandTest extends TestCase
{
    /**
     * But : Vérifier que la commande est exécutable avec la limite par défaut.
     *
     * Entrée : Exécution sans argument.
     * Résultat attendu : Code SUCCESS et résumé de traitement affiché.
     */
    public function test_it_executes_with_default_limit(): void
    {
        $consumer = $this->createMock(QueueConsumerInterface::class);

        $result = new QueueConsumeResult();
        $result->incrementProcessed();

        $consumer
            ->expects(self::once())
            ->method('consume')
            ->with(100)
            ->willReturn($result);

        $commandTester = new CommandTester(
            new ProcessQueueCommand($consumer),
        );

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        self::assertStringContainsString(
            'Queue process completed',
            $commandTester->getDisplay(),
        );
    }

    /**
     * But : Vérifier que la commande accepte une limite explicite.
     *
     * Entrée : limit=25.
     * Résultat attendu : consume(25) est appelé et la commande retourne SUCCESS.
     */
    public function test_it_uses_custom_limit(): void
    {
        $consumer = $this->createMock(QueueConsumerInterface::class);

        $consumer
            ->expects(self::once())
            ->method('consume')
            ->with(25)
            ->willReturn(new QueueConsumeResult());

        $commandTester = new CommandTester(
            new ProcessQueueCommand($consumer),
        );

        $exitCode = $commandTester->execute([
            'limit' => '25',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * But : Vérifier que la commande échoue proprement si la limite est invalide.
     *
     * Entrée : limit='abc'.
     * Résultat attendu : Code FAILURE et message d'erreur explicite.
     */
    public function test_it_fails_with_invalid_limit(): void
    {
        $consumer = $this->createMock(QueueConsumerInterface::class);

        $consumer
            ->expects(self::never())
            ->method('consume');

        $commandTester = new CommandTester(
            new ProcessQueueCommand($consumer),
        );

        $exitCode = $commandTester->execute([
            'limit' => 'abc',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        self::assertStringContainsString(
            'Queue process failed.',
            $commandTester->getDisplay(),
        );
    }

    /**
     * But : Vérifier qu'une exception du consumer est capturée sans erreur fatale.
     *
     * Entrée : consume() lève RuntimeException.
     * Résultat attendu : Code FAILURE avec message d'erreur rendu.
     */
    public function test_it_handles_consumer_exception_without_fatal_error(): void
    {
        $consumer = $this->createMock(QueueConsumerInterface::class);

        $consumer
            ->expects(self::once())
            ->method('consume')
            ->willThrowException(new \RuntimeException('queue unavailable'));

        $commandTester = new CommandTester(
            new ProcessQueueCommand($consumer),
        );

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);

        self::assertStringContainsString(
            'queue unavailable',
            $commandTester->getDisplay(),
        );
    }
}
