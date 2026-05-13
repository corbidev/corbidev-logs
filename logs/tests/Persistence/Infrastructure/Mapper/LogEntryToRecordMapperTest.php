<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Infrastructure\Mapper;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IngestionWarning;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
use App\Log\Enum\LogLevel;
use App\Persistence\Infrastructure\Entity\LogRecord;
use App\Persistence\Infrastructure\Mapper\LogEntryToRecordMapper;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogEntryToRecordMapper.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité mapping
 * - robustesse persistence
 * - sanitation SQL
 * - cohérence Domain → Infrastructure
 * - stabilité JSON
 * - stabilité UTF-8
 * - robustesse ingestionWarnings
 *
 * IMPORTANT :
 * ------------
 * Ce mapper ne doit jamais :
 * - modifier les invariants métier
 * - recalculer le fingerprint
 * - throw sur payload hostile
 * - perdre des données métier valides
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - mapping complet
 * - sanitation SQL
 * - bornes JSON
 * - normalisation profondeur
 * - stabilité persistence
 * - compatibilité ingestionWarnings
 */
#[CoversClass(LogEntryToRecordMapper::class)]
final class LogEntryToRecordMapperTest extends TestCase
{
    private LogEntryToRecordMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LogEntryToRecordMapper();
    }

    public function testItMapsLogEntry(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );
    }

    public function testItMapsExternalId(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $entry->id(),
            $record->getExternalId(),
        );
    }

    public function testItMapsProjectId(): void
    {
        $record = $this->mapper->map(
            999,
            $this->createEntry(),
        );

        self::assertSame(
            '999',
            $record->getProjectId(),
        );
    }

    public function testItMapsRequestId(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'req_checkout_123',
            $record->getRequestId(),
        );
    }

    public function testItMapsFingerprint(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'abcdef1234567890',
            $record->getFingerprint(),
        );
    }

    public function testItMapsLevel(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'error',
            $record->getLevel(),
        );
    }

    public function testItMapsHttpStatus(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            500,
            $record->getHttpStatus(),
        );
    }

    public function testItMapsDomain(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'billing',
            $record->getDomain(),
        );
    }

    public function testItMapsUri(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            '/orders',
            $record->getUri(),
        );
    }

    public function testItMapsMethod(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'POST',
            $record->getMethod(),
        );
    }

    public function testItMapsUserAgent(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $record->getUserAgent(),
        );
    }

    public function testItMapsEnvironment(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'prod',
            $record->getEnv(),
        );
    }

    public function testItMapsClient(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'checkout-app',
            $record->getClient(),
        );
    }

    public function testItMapsMessage(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'Payment failed',
            $record->getMessage(),
        );
    }

    public function testItMapsContextJson(): void
    {
        $entry = $this->createEntry(
            context: [
                'userId' => 42,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            [
                'userId' => 42,
            ],
            $record->getContextJson(),
        );
    }

    public function testItMapsExtraJson(): void
    {
        $entry = $this->createEntry(
            extra: [
                'memory' => 123,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            [
                'memory' => 123,
            ],
            $record->getExtraJson(),
        );
    }

    public function testItMapsCreatedAt(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            createdAt: $date,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $date,
            $record->getCreatedAt(),
        );
    }

    public function testItMapsClientDate(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            clientDate: $date,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $date,
            $record->getClientDate(),
        );
    }

    public function testItMapsIp(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            '127.0.0.1',
            $record->getIp(),
        );
    }

    public function testItMapsIngestionWarnings(): void
    {
        $entry = $this->createEntry(
            ingestionWarnings: [
                new IngestionWarning(
                    type: IngestionWarningType::INVALID_MESSAGE,
                    field: 'message',
                    original: null,
                    fallback: 'unknown error',
                ),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertCount(
            1,
            $record->getIngestionWarningsJson(),
        );
    }

    public function testItSanitizesControlCharacters(): void
    {
        $entry = $this->createEntry(
            context: [
                "bad\0key" => "value\0with\0null",
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertArrayHasKey(
            'badkey',
            $record->getContextJson(),
        );

        self::assertSame(
            'valuewithnull',
            $record
                ->getContextJson()['badkey'],
        );
    }

    public function testItConvertsObjects(): void
    {
        $entry = $this->createEntry(
            context: [
                'object' => new \stdClass(),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            '[object:stdClass]',
            $record
                ->getContextJson()['object'],
        );
    }

    public function testItConvertsResources(): void
    {
        $resource = fopen(
            'php://memory',
            'r',
        );

        self::assertIsResource(
            $resource,
        );

        $entry = $this->createEntry(
            context: [
                'resource' => $resource,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        fclose(
            $resource,
        );

        self::assertSame(
            '[resource]',
            $record
                ->getContextJson()['resource'],
        );
    }

    public function testItNormalizesDeepContext(): void
    {
        $entry = $this->createEntry(
            context: [
                'a' => [
                    'b' => [
                        'c' => [
                            'd' => [
                                'e' => [
                                    'f' => 'overflow',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'max_depth_reached',
            $record
                ->getContextJson()['a']['b']['c']['d']['e']['__truncated__'],
        );
    }

    public function testItLimitsHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key-' . $i] = $i;
        }

        $entry = $this->createEntry(
            context: $context,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertLessThanOrEqual(
            51,
            count(
                $record->getContextJson(),
            ),
        );

        self::assertArrayHasKey(
            '__truncated__',
            $record->getContextJson(),
        );
    }

    public function testItTruncatesHugeStrings(): void
    {
        $entry = $this->createEntry(
            context: [
                'huge' => str_repeat(
                    'A',
                    10000,
                ),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            1000,
            mb_strlen(
                $record
                    ->getContextJson()['huge'],
            ),
        );
    }

    public function testItRejectsHugeStringsAtDomainLevel(): void
    {
        /**
         * IMPORTANT :
         * ------------
         * Le mapper ne doit jamais recevoir
         * un LogEntry invalide.
         *
         * Les limites sont garanties
         * par le Domain.
         */

        $this->expectException(
            \Throwable::class,
        );

        $this->createEntry(
            message: str_repeat(
                'A',
                10000,
            ),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     * @param list<IngestionWarning> $ingestionWarnings
     */
    private function createEntry(
        string $message = 'Payment failed',
        array $context = [],
        array $extra = [],
        array $ingestionWarnings = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
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

            requestId: new RequestId(
                'req_checkout_123',
            ),

            request: new Request(
                uri: new Uri('/orders'),
                method: 'POST',
                userAgent: 'Mozilla/5.0',
            ),

            ipAddress: new IpAddress(
                '127.0.0.1',
            ),

            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),

            context: $context,

            extra: $extra,

            ingestionWarnings: $ingestionWarnings,

            clientDate: $clientDate,

            createdAt: $createdAt,
        );
    }
}
