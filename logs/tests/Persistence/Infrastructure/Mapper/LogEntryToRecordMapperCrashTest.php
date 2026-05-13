<?php

declare(strict_types=1);

namespace App\Tests\Crash\Persistence\Infrastructure\Mapper;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Crash tests critiques de LogEntryToRecordMapper.
 *
 * OBJECTIFS :
 * -----------
 * - garantir absence de crash
 * - garantir stabilité mémoire
 * - tester payloads hostiles
 * - tester sanitation SQL
 * - tester normalisation JSON
 * - tester robustesse UTF-8
 * - tester limites récursives
 * - tester compatibilité persistence
 *
 * IMPORTANT :
 * ------------
 * Le mapper :
 * - ne doit jamais throw sur payload hostile
 * - doit toujours retourner un LogRecord valide
 * - doit protéger la couche SQL
 * - doit normaliser les données JSON
 * - doit borner profondeur et volume
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - gros payloads
 * - invalid UTF-8
 * - binary payloads
 * - ressources PHP
 * - objets hostiles
 * - récursion profonde
 * - sanitation SQL
 * - limites JSON
 * - ingestionWarnings
 */
#[CoversClass(LogEntryToRecordMapper::class)]
final class LogEntryToRecordMapperCrashTest extends TestCase
{
    private LogEntryToRecordMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LogEntryToRecordMapper();
    }

    public function testItHandlesHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; ++$i) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: $context,
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        /**
         * Le mapper doit borner à 50 items.
         */
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

    public function testItHandlesHugeExtra(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; ++$i) {
            $extra['key-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                extra: $extra,
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertLessThanOrEqual(
            51,
            count(
                $record->getExtraJson(),
            ),
        );
    }

    public function testItHandlesBinaryPayloads(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'binary' => "\x00\x01\x02",
                ],
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertIsArray(
            $record->getContextJson(),
        );
    }

    public function testItHandlesInvalidUtf8Payloads(): void
    {
        $payload = hex2bin(
            'b131',
        );

        self::assertNotFalse(
            $payload,
        );

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'invalid' => $payload,
                ],
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );
    }

    public function testItHandlesHostilePayloads(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'xss' => '<script>alert(1)</script>',
                    'sql' => "'; DROP TABLE logs; --",
                    'path' => '../../../../../etc/passwd',
                    'shell' => '$(rm -rf /)',
                    'php' => '<?php phpinfo();',
                ],
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertIsArray(
            $record->getContextJson(),
        );
    }

    public function testItRejectsHugeStringsAtDomainLevel(): void
    {
        /**
         * IMPORTANT :
         * ------------
         * Le mapper ne doit jamais recevoir
         * de LogEntry invalide.
         *
         * Le Domain protège déjà
         * les invariants métier.
         */

        $this->expectException(
            \Throwable::class,
        );

        $this->createEntry(
            message: str_repeat(
                'ERROR ',
                100000,
            ),
        );
    }

    public function testItHandlesHugeNestedArrays(): void
    {
        $payload = [];

        $current = &$payload;

        for ($i = 0; $i < 50; ++$i) {
            $current['nested'] = [];

            $current = &$current['nested'];
        }

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: $payload,
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertIsArray(
            $record->getContextJson(),
        );
    }

    public function testItLimitsMaxDepth(): void
    {
        $payload = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => [
                                    'g' => 'too-deep',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: $payload,
            ),
        );

        self::assertSame(
            'max_depth_reached',
            $record
                ->getContextJson()['a']['b']['c']['d']['e']['__truncated__'],
        );
    }

    public function testItHandlesResources(): void
    {
        $resource = fopen(
            'php://memory',
            'r',
        );

        self::assertIsResource(
            $resource,
        );

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'resource' => $resource,
                ],
            ),
        );

        fclose(
            $resource,
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertSame(
            '[resource]',
            $record
                ->getContextJson()['resource'],
        );
    }

    public function testItHandlesObjects(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'object' => new stdClass(),
                ],
            ),
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );

        self::assertSame(
            '[object:stdClass]',
            $record
                ->getContextJson()['object'],
        );
    }

    public function testItSanitizesControlCharacters(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    "bad\0key" => "value\0with\0null",
                ],
            ),
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

    public function testItTruncatesHugeStrings(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'huge' => str_repeat(
                        'A',
                        10000,
                    ),
                ],
            ),
        );

        self::assertSame(
            1000,
            mb_strlen(
                $record
                    ->getContextJson()['huge'],
            ),
        );
    }

    public function testItHandlesDateTimeObjects(): void
    {
        $date = new \DateTimeImmutable();

        $record = $this->mapper->map(
            42,
            $this->createEntry(
                context: [
                    'date' => $date,
                ],
            ),
        );

        self::assertSame(
            $date->format(
                \DateTimeInterface::ATOM,
            ),
            $record
                ->getContextJson()['date'],
        );
    }

    public function testItHandlesIngestionWarnings(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(
                ingestionWarnings: [
                    new IngestionWarning(
                        type: IngestionWarningType::INVALID_MESSAGE,
                        field: 'message',
                        originalValue: null,
                        correctedValue: 'unknown error',
                    ),
                ],
            ),
        );

        self::assertCount(
            1,
            $record->getIngestionWarningsJson(),
        );
    }

    public function testItHandlesRepeatedMappings(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            $record = $this->mapper->map(
                $i,
                $this->createEntry(
                    context: [
                        'iteration' => $i,
                    ],
                ),
            );

            self::assertInstanceOf(
                LogRecord::class,
                $record,
            );
        }
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
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: LogLevel::ERROR,
            domain: 'billing',
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),

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
        );
    }
}