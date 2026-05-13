<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Factory;

use App\Log\Application\Factory\LogEntryFactory;
use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogEntryFactory.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - protection ingestion
 * - stabilité des warnings
 *
 * IMPORTANT :
 * ------------
 * La factory doit :
 * - ne jamais crash
 * - tolérer les payloads hostiles
 * - générer requestId si absent
 * - stabiliser les anciennes structures
 * - rester rétrocompatible ingestion
 * - tracer les corrections ingestion
 */
#[CoversClass(LogEntryFactory::class)]
final class LogEntryFactoryTest extends TestCase
{
    private LogEntryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LogEntryFactory();
    }

    public function testItCreatesLogEntry(): void
    {
        $entry = $this->factory->create([
            'message' => 'Payment failed',
            'level' => 'error',
            'domain' => 'billing',
            'env' => 'prod',
            'httpStatus' => 500,
            'client' => 'checkout-app',

            'requestId' => 'req_checkout_123',

            'request' => [
                'uri' => '/orders',
                'method' => 'POST',
                'userAgent' => 'Mozilla/5.0',
            ],

            'ip' => '127.0.0.1',

            'fingerprint' => 'abcdef1234567890',
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertFalse(
            $entry->hasIngestionWarnings(),
        );
    }

    public function testItCreatesRequestId(): void
    {
        $entry = $this->factory->create([
            'requestId' => 'REQ_CHECKOUT_123',
        ]);

        self::assertSame(
            'req_checkout_123',
            $entry
                ->requestId()
                ->value(),
        );
    }

    public function testItGeneratesRequestIdWhenMissing(): void
    {
        $entry = $this->factory->create([]);

        self::assertStringStartsWith(
            'req_',
            $entry
                ->requestId()
                ->value(),
        );
    }

    public function testItGeneratesRequestIdWhenInvalid(): void
    {
        $entry = $this->factory->create([
            'requestId' => '<script>',
        ]);

        self::assertStringStartsWith(
            'req_',
            $entry
                ->requestId()
                ->value(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_REQUEST_ID,
        );
    }

    public function testItNormalizesMessage(): void
    {
        $entry = $this->factory->create([
            'message' => '   Payment failed   ',
        ]);

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );
    }

    public function testItFallsBackForInvalidMessage(): void
    {
        $entry = $this->factory->create([
            'message' => null,
        ]);

        self::assertSame(
            'unknown error',
            $entry->message(),
        );
    }

    public function testItTruncatesHugeMessage(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'A',
                5000,
            ),
        ]);

        self::assertLessThanOrEqual(
            1000,
            mb_strlen(
                $entry->message(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::MESSAGE_TRUNCATED,
        );
    }

    public function testItNormalizesDomain(): void
    {
        $entry = $this->factory->create([
            'domain' => ' BILLING ',
        ]);

        self::assertSame(
            'billing',
            $entry->domain(),
        );
    }

    public function testItFallsBackForInvalidDomain(): void
    {
        $entry = $this->factory->create([
            'domain' => null,
        ]);

        self::assertSame(
            'unknown',
            $entry->domain(),
        );
    }

    public function testItCreatesLogLevel(): void
    {
        $entry = $this->factory->create([
            'level' => 'critical',
        ]);

        self::assertSame(
            LogLevel::CRITICAL,
            $entry->level(),
        );
    }

    public function testItFallsBackLogLevel(): void
    {
        $entry = $this->factory->create([
            'level' => 'INVALID',
        ]);

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_LEVEL,
        );
    }

    public function testItCreatesEnvironment(): void
    {
        $entry = $this->factory->create([
            'env' => 'staging',
        ]);

        self::assertSame(
            Environment::Staging,
            $entry->environment(),
        );
    }

    public function testItFallsBackEnvironment(): void
    {
        $entry = $this->factory->create([
            'env' => 'INVALID',
        ]);

        self::assertSame(
            Environment::Production,
            $entry->environment(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_ENVIRONMENT,
        );
    }

    public function testItCreatesRequest(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'uri' => '/orders',
                'method' => 'POST',
                'userAgent' => 'Mozilla/5.0',
            ],
        ]);

        self::assertSame(
            '/orders',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'POST',
            $entry
                ->request()
                ->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    public function testItSupportsLegacyRequestPayload(): void
    {
        $entry = $this->factory->create([
            'uri' => '/legacy',
            'method' => 'PUT',
            'userAgent' => 'LegacyAgent',
        ]);

        self::assertSame(
            '/legacy',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'PUT',
            $entry
                ->request()
                ->method(),
        );

        self::assertSame(
            'LegacyAgent',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    public function testItFallsBackRequestMethod(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'method' => null,
            ],
        ]);

        self::assertSame(
            'GET',
            $entry
                ->request()
                ->method(),
        );
    }

    public function testItTruncatesHugeUserAgent(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'userAgent' => str_repeat(
                    'Mozilla/5.0 ',
                    5000,
                ),
            ],
        ]);

        self::assertLessThanOrEqual(
            500,
            mb_strlen(
                $entry
                    ->request()
                    ->userAgent(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::USER_AGENT_TRUNCATED,
        );
    }

    public function testItFallsBackUserAgent(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'userAgent' => null,
            ],
        ]);

        self::assertSame(
            '',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    public function testItCreatesContext(): void
    {
        $entry = $this->factory->create([
            'context' => [
                'userId' => 42,
            ],
        ]);

        self::assertSame(
            [
                'userId' => 42,
            ],
            $entry->context(),
        );
    }

    public function testItCreatesExtra(): void
    {
        $entry = $this->factory->create([
            'extra' => [
                'memory' => 123,
            ],
        ]);

        self::assertSame(
            [
                'memory' => 123,
            ],
            $entry->extra(),
        );
    }

    public function testItFallsBackInvalidContext(): void
    {
        $entry = $this->factory->create([
            'context' => 'invalid',
        ]);

        self::assertSame(
            [],
            $entry->context(),
        );
    }

    public function testItFallsBackInvalidExtra(): void
    {
        $entry = $this->factory->create([
            'extra' => 'invalid',
        ]);

        self::assertSame(
            [],
            $entry->extra(),
        );
    }

    public function testItFallsBackInvalidIp(): void
    {
        $entry = $this->factory->create([
            'ip' => '999.999.999.999',
        ]);

        self::assertSame(
            '127.0.0.1',
            $entry
                ->ipAddress()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_IP,
        );
    }

    public function testItFallsBackInvalidFingerprint(): void
    {
        $entry = $this->factory->create([
            'fingerprint' => 'INVALID',
        ]);

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $entry
                ->fingerprint()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::FINGERPRINT_REGENERATED,
        );
    }

    public function testItFallsBackInvalidUri(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'uri' => str_repeat(
                    '/orders',
                    1000,
                ),
            ],
        ]);

        self::assertSame(
            '/',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_URI,
        );
    }

    /**
     * IMPORTANT :
     * ------------
     * Les anciens clients peuvent encore
     * envoyer un champ "tags".
     *
     * La factory doit l'ignorer sans crash.
     */
    public function testItIgnoresLegacyTagsPayload(): void
    {
        $entry = $this->factory->create([
            'tags' => [
                'feature' => 'checkout',
                'region' => 'eu',
            ],
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItCreatesGeneratedUuid(): void
    {
        $entry = $this->factory->create([]);

        self::assertNotEmpty(
            $entry->id(),
        );
    }

    public function testItKeepsExistingUuid(): void
    {
        $id = '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21';

        $entry = $this->factory->create([
            'id' => $id,
        ]);

        self::assertSame(
            $id,
            $entry->id(),
        );
    }

    public function testItCreatesDates(): void
    {
        $entry = $this->factory->create([
            'createdAt' => '2025-01-01 10:00:00',
        ]);

        self::assertSame(
            '2025-01-01',
            $entry
                ->createdAt()
                ->format('Y-m-d'),
        );
    }

    public function testItHandlesInvalidDate(): void
    {
        $entry = $this->factory->create([
            'createdAt' => 'INVALID_DATE',
        ]);

        self::assertNotNull(
            $entry->createdAt(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_CREATED_AT,
        );
    }

    public function testItNeverCrashesWithHostilePayload(): void
    {
        $entry = $this->factory->create([
            'message' => [
                'invalid',
            ],

            'domain' => new \stdClass(),

            'requestId' => [
                'bad',
            ],

            'request' => 'invalid',

            'context' => 'bad',

            'extra' => false,
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItExposesStableWarningsStructure(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'A',
                5000,
            ),

            'ip' => '999.999.999.999',
        ]);

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        foreach (
            $entry->ingestionWarnings()
            as $warning
        ) {
            $data = $warning->toArray();

            self::assertArrayHasKey(
                'field',
                $data,
            );

            self::assertArrayHasKey(
                'type',
                $data,
            );

            self::assertArrayHasKey(
                'original',
                $data,
            );

            self::assertArrayHasKey(
                'fallback',
                $data,
            );
        }
    }

    private function assertContainsWarningType(
        LogEntry $entry,
        IngestionWarningType $expected,
    ): void {
        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            $expected->value,
            $types,
        );
    }
}