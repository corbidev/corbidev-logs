<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Infrastructure\Entity;

use App\Persistence\Infrastructure\Entity\LogRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogRecord.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité mapping
 * - robustesse getters/setters
 * - cohérence persistence
 * - absence de logique métier
 * - stabilité long terme
 *
 * IMPORTANT :
 * ------------
 * Cette entity Doctrine doit rester :
 * - anémique
 * - prédictible
 * - stable
 * - purement technique
 * - sans comportement métier
 *
 * GARANTIES :
 * ------------
 * - aucun traitement implicite
 * - aucun effet de bord
 * - aucun mapping magique
 * - aucune mutation cachée
 */
#[CoversClass(LogRecord::class)]
final class LogRecordTest extends TestCase
{
    public function testItStoresExternalId(): void
    {
        $record = new LogRecord();

        $record->setExternalId(
            '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21',
        );

        self::assertSame(
            '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21',
            $record->getExternalId(),
        );
    }

    public function testItStoresProjectId(): void
    {
        $record = new LogRecord();

        $record->setProjectId(
            '42',
        );

        self::assertSame(
            '42',
            $record->getProjectId(),
        );
    }

    public function testItStoresFingerprint(): void
    {
        $record = new LogRecord();

        $record->setFingerprint(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $record->getFingerprint(),
        );
    }

    public function testItStoresRequestId(): void
    {
        $record = new LogRecord();

        $record->setRequestId(
            'req_checkout_123',
        );

        self::assertSame(
            'req_checkout_123',
            $record->getRequestId(),
        );
    }

    public function testItStoresLevel(): void
    {
        $record = new LogRecord();

        $record->setLevel(
            'error',
        );

        self::assertSame(
            'error',
            $record->getLevel(),
        );
    }

    public function testItStoresHttpStatus(): void
    {
        $record = new LogRecord();

        $record->setHttpStatus(
            500,
        );

        self::assertSame(
            500,
            $record->getHttpStatus(),
        );
    }

    public function testItStoresDomain(): void
    {
        $record = new LogRecord();

        $record->setDomain(
            'billing',
        );

        self::assertSame(
            'billing',
            $record->getDomain(),
        );
    }

    public function testItStoresUri(): void
    {
        $record = new LogRecord();

        $record->setUri(
            '/orders',
        );

        self::assertSame(
            '/orders',
            $record->getUri(),
        );
    }

    public function testItStoresMethod(): void
    {
        $record = new LogRecord();

        $record->setMethod(
            'POST',
        );

        self::assertSame(
            'POST',
            $record->getMethod(),
        );
    }

    public function testItStoresNullableMethod(): void
    {
        $record = new LogRecord();

        $record->setMethod(
            null,
        );

        self::assertNull(
            $record->getMethod(),
        );
    }

    public function testItStoresUserAgent(): void
    {
        $record = new LogRecord();

        $record->setUserAgent(
            'Mozilla/5.0',
        );

        self::assertSame(
            'Mozilla/5.0',
            $record->getUserAgent(),
        );
    }

    public function testItStoresNullableUserAgent(): void
    {
        $record = new LogRecord();

        $record->setUserAgent(
            null,
        );

        self::assertNull(
            $record->getUserAgent(),
        );
    }

    public function testItStoresEnvironment(): void
    {
        $record = new LogRecord();

        $record->setEnv(
            'prod',
        );

        self::assertSame(
            'prod',
            $record->getEnv(),
        );
    }

    public function testItStoresClient(): void
    {
        $record = new LogRecord();

        $record->setClient(
            'checkout-app',
        );

        self::assertSame(
            'checkout-app',
            $record->getClient(),
        );
    }

    public function testItStoresMessage(): void
    {
        $record = new LogRecord();

        $record->setMessage(
            'Payment failed',
        );

        self::assertSame(
            'Payment failed',
            $record->getMessage(),
        );
    }

    public function testItStoresContextJson(): void
    {
        $context = [
            'userId' => 42,
        ];

        $record = new LogRecord();

        $record->setContextJson(
            $context,
        );

        self::assertSame(
            $context,
            $record->getContextJson(),
        );
    }

    public function testItStoresEmptyContextJson(): void
    {
        $record = new LogRecord();

        $record->setContextJson(
            [],
        );

        self::assertSame(
            [],
            $record->getContextJson(),
        );
    }

    public function testItStoresExtraJson(): void
    {
        $extra = [
            'memory' => 123,
        ];

        $record = new LogRecord();

        $record->setExtraJson(
            $extra,
        );

        self::assertSame(
            $extra,
            $record->getExtraJson(),
        );
    }

    public function testItStoresEmptyExtraJson(): void
    {
        $record = new LogRecord();

        $record->setExtraJson(
            [],
        );

        self::assertSame(
            [],
            $record->getExtraJson(),
        );
    }

    public function testItStoresIngestionWarningsJson(): void
    {
        $warnings = [
            [
                'type' => 'message_truncated',
                'field' => 'message',
                'message' => 'Message truncated',
            ],
        ];

        $record = new LogRecord();

        $record->setIngestionWarningsJson(
            $warnings,
        );

        self::assertSame(
            $warnings,
            $record->getIngestionWarningsJson(),
        );
    }

    public function testItStoresEmptyIngestionWarningsJson(): void
    {
        $record = new LogRecord();

        $record->setIngestionWarningsJson(
            [],
        );

        self::assertSame(
            [],
            $record->getIngestionWarningsJson(),
        );
    }

    public function testItStoresCreatedAt(): void
    {
        $date = new DateTimeImmutable();

        $record = new LogRecord();

        $record->setCreatedAt(
            $date,
        );

        self::assertSame(
            $date,
            $record->getCreatedAt(),
        );
    }

    public function testItStoresClientDate(): void
    {
        $date = new DateTimeImmutable();

        $record = new LogRecord();

        $record->setClientDate(
            $date,
        );

        self::assertSame(
            $date,
            $record->getClientDate(),
        );
    }

    public function testItStoresNullableClientDate(): void
    {
        $record = new LogRecord();

        $record->setClientDate(
            null,
        );

        self::assertNull(
            $record->getClientDate(),
        );
    }

    public function testItStoresIp(): void
    {
        $record = new LogRecord();

        $record->setIp(
            '127.0.0.1',
        );

        self::assertSame(
            '127.0.0.1',
            $record->getIp(),
        );
    }

    public function testItStoresNullableIp(): void
    {
        $record = new LogRecord();

        $record->setIp(
            null,
        );

        self::assertNull(
            $record->getIp(),
        );
    }

    public function testItStoresNullableIdBeforePersistence(): void
    {
        $record = new LogRecord();

        self::assertNull(
            $record->getId(),
        );
    }

    public function testItSupportsRepeatedGetterCalls(): void
    {
        $record = $this->createRecord();

        for ($i = 0; $i < 1000; ++$i) {
            self::assertSame(
                'external-id',
                $record->getExternalId(),
            );

            self::assertSame(
                'billing',
                $record->getDomain(),
            );

            self::assertSame(
                'Payment failed',
                $record->getMessage(),
            );
        }
    }

    private function createRecord(): LogRecord
    {
        $record = new LogRecord();

        $record->setExternalId(
            'external-id',
        );

        $record->setProjectId(
            '1',
        );

        $record->setFingerprint(
            'abcdef1234567890',
        );

        $record->setRequestId(
            'req_test',
        );

        $record->setLevel(
            'error',
        );

        $record->setHttpStatus(
            500,
        );

        $record->setDomain(
            'billing',
        );

        $record->setUri(
            '/orders',
        );

        $record->setMethod(
            'POST',
        );

        $record->setUserAgent(
            'Mozilla/5.0',
        );

        $record->setEnv(
            'prod',
        );

        $record->setClient(
            'checkout-app',
        );

        $record->setMessage(
            'Payment failed',
        );

        $record->setContextJson([]);

        $record->setExtraJson([]);

        $record->setIngestionWarningsJson([]);

        $record->setCreatedAt(
            new DateTimeImmutable(),
        );

        $record->setIp(
            '127.0.0.1',
        );

        return $record;
    }
}