<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use App\Persistence\Application\PersistLogBatchHandler;
use App\Persistence\Application\PersistLogBatchRequest;
use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de PersistLogBatchHandler.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - cohérence batch
 */
final class PersistLogBatchHandlerTest extends TestCase
{
    /**
     * But : Vérifier que le handler persiste correctement un lot de LogEntry.
     *
     * Entrée : PersistLogBatchRequest avec 2 LogEntry valides, writer retournant success
     * Résultat attendu : writer.persist() appelé 1 fois, result.isSuccess() = true
     */
    public function testItPersistsBatch(): void
    {
        $entries = [
            $this->createEntry(
                requestId: 'req_batch_1',
            ),

            $this->createEntry(
                requestId: 'req_batch_2',
            ),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with($entries)
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 2,
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                $entries,
            ),
        );

        self::assertTrue(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler ne persiste pas un lot vide.
     *
     * Entrée : PersistLogBatchRequest vide ([])
     * Résultat attendu : Writer jamais appelé, hasPersistedLogs() = false
     */
    public function testItDoesNotPersistEmptyBatch(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::never())
            ->method('persist');

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                [],
            ),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );
    }

    /**
     * But : Vérifier que le handler retourne failure quand le writer échoue.
     *
     * Entrée : PersistLogBatchRequest valide, writer retournant failure
     * Résultat attendu : result.isSuccess() = false
     */
    public function testItReturnsFailureWhenWriterFails(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->willReturn(
                PersistenceResult::failure(
                    failedCount: 1,
                    errors: [
                        'sql error',
                    ],
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest([
                $this->createEntry(),
            ]),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler retourne toujours un PersistenceResult même pour un lot vide.
     *
     * Entrée : PersistLogBatchRequest vide
     * Résultat attendu : Instance de PersistenceResult retournée
     */
    public function testItAlwaysReturnsPersistenceResult(): void
    {
        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::never())
            ->method('persist');

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                [],
            ),
        );

        self::assertInstanceOf(
            PersistenceResult::class,
            $result,
        );
    }

    /**
     * But : Vérifier que l'ordre des entrées est préservé lors de la persistance.
     *
     * Entrée : Deux LogEntry avec messages 'first' et 'second'
     * Résultat attendu : Les messages sont persistés dans l'ordre 'first', 'second'
     */
    public function testItPreservesEntriesOrder(): void
    {
        $entries = [
            $this->createEntry(
                message: 'first',
                requestId: 'req_first',
            ),

            $this->createEntry(
                message: 'second',
                requestId: 'req_second',
            ),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with(
                self::callback(
                    static function (
                        array $entries,
                    ): bool {
                        return $entries[0]->message()
                                === 'first'
                            && $entries[1]->message()
                                === 'second';
                    },
                ),
            )
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 2,
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                $entries,
            ),
        );

        self::assertTrue(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que le handler gère correctement un lot d'une seule entrée.
     *
     * Entrée : PersistLogBatchRequest avec 1 LogEntry
     * Résultat attendu : result.isSuccess() = true
     */
    public function testItHandlesSingleEntry(): void
    {
        $entries = [
            $this->createEntry(),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with($entries)
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 1,
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                $entries,
            ),
        );

        self::assertTrue(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que les requestIds des entrées sont préservés dans l'ordre.
     *
     * Entrée : Deux LogEntry avec requestIds 'req_checkout_1' et 'req_checkout_2'
     * Résultat attendu : Les requestIds sont présents dans l'ordre dans les entrées persistées
     */
    public function testItPreservesRequestIds(): void
    {
        $entries = [
            $this->createEntry(
                requestId: 'req_checkout_1',
            ),

            $this->createEntry(
                requestId: 'req_checkout_2',
            ),
        ];

        $writer = $this->createMock(
            LogWriterInterface::class,
        );

        $writer
            ->expects(self::once())
            ->method('persist')
            ->with(
                self::callback(
                    static function (
                        array $entries,
                    ): bool {
                        return $entries[0]
                                ->requestId()
                                ->value()
                                === 'req_checkout_1'
                            && $entries[1]
                                ->requestId()
                                ->value()
                                === 'req_checkout_2';
                    },
                ),
            )
            ->willReturn(
                PersistenceResult::success(
                    persistedCount: 2,
                ),
            );

        $handler = new PersistLogBatchHandler(
            $writer,
        );

        $result = $handler->handle(
            new PersistLogBatchRequest(
                $entries,
            ),
        );

        self::assertTrue(
            $result->isSuccess(),
        );
    }

    private function createEntry(
        string $message = 'Payment failed',
        string $requestId = 'req_checkout_test',
    ): LogEntry {
        return new LogEntry(
            message: $message,

            level: LogLevel::ERROR,

            domain: 'billing',

            environment: Environment::Production,

            httpStatus: new HttpStatus(
                500,
            ),

            client: new Client(
                'checkout-app',
            ),

            request: new Request(
                uri: new Uri(
                    '/orders',
                ),

                method: 'POST',

                userAgent: 'Mozilla/5.0',
            ),

            ipAddress: new IpAddress(
                '127.0.0.1',
            ),

            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),

            requestId: new RequestId(
                $requestId,
            ),
        );
    }
}