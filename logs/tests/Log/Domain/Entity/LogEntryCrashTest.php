<?php

declare(strict_types=1);

namespace App\Tests\Crash\Log\Domain\Entity;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\Exception\InvalidLogEntryException;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests critiques de LogEntry.
 *
 * OBJECTIFS :
 * -----------
 * - garantir absence de crash
 * - garantir robustesse mémoire
 * - garantir stabilité domaine
 * - garantir stabilité serialization
 * - tester payloads hostiles
 * - tester payloads binaires
 * - tester structures volumineuses
 *
 * IMPORTANT :
 * ------------
 * Ces crash tests doivent :
 * - rester déterministes
 * - rester bornés
 * - ne jamais dépendre de Symfony runtime
 * - ne jamais dépendre de Doctrine
 * - ne jamais dépendre du filesystem
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - gros payloads
 * - binary payloads
 * - invalid UTF-8
 * - injections
 * - structures profondes
 * - gros tableaux
 * - sérialisation stable
 * - robustesse mémoire
 * - stabilité ingestionWarnings
 */
#[CoversClass(LogEntry::class)]
final class LogEntryCrashTest extends TestCase
{
    public function testItHandlesHugeContextWithoutCrash(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; ++$i) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $entry = $this->createEntry(
            context: $context,
        );

        self::assertCount(
            10000,
            $entry->context(),
        );
    }

    public function testItHandlesHugeExtraWithoutCrash(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; ++$i) {
            $extra['extra-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $entry = $this->createEntry(
            extra: $extra,
        );

        self::assertCount(
            10000,
            $entry->extra(),
        );
    }

    public function testItHandlesDeepNestedPayloadWithoutCrash(): void
    {
        $payload = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => 'deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $entry = $this->createEntry(
            context: $payload,
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesHugeStringPayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'X',
            5_000_000,
        );

        $entry = $this->createEntry(
            context: [
                'huge' => $payload,
            ],
        );

        self::assertSame(
            $payload,
            $entry->context()['huge'],
        );
    }

    public function testItHandlesBinaryPayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'binary' => "\x00\x01\x02",
            ],
            extra: [
                'binary' => "\x00\x01\x02",
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
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

        $entry = $this->createEntry(
            context: [
                'utf8' => $payload,
            ],
            extra: [
                'utf8' => $payload,
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesNullBytesWithoutCrash(): void
    {
        $payload = "abc\0def";

        $entry = $this->createEntry(
            context: [
                'null-byte' => $payload,
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesHostilePayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'xss' => '<script>alert(1)</script>',
                'sql' => "'; DROP TABLE logs; --",
                'path' => '../../../../../etc/passwd',
                'shell' => '$(rm -rf /)',
                'php' => '<?php phpinfo();',
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesJsonPayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'json' => json_encode([
                    'a' => 1,
                    'b' => 2,
                ]),
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesUnicodePayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'unicode' => '🔥 éèà 中文 русский',
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesLargeSerializationWithoutCrash(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key-' . $i] = [
                'memory' => str_repeat(
                    'A',
                    500,
                ),
            ];
        }

        $entry = $this->createEntry(
            context: $context,
        );

        $serialized = $entry->toArray();

        self::assertCount(
            5000,
            $serialized['context'],
        );
    }

    public function testItHandlesManyInstancesWithoutCrash(): void
    {
        $entries = [];

        for ($i = 0; $i < 1000; ++$i) {
            $entries[] = $this->createEntry(
                context: [
                    'iteration' => $i,
                ],
            );
        }

        self::assertCount(
            1000,
            $entries,
        );
    }

    public function testItHandlesRepeatedSerializationWithoutCrash(): void
    {
        $entry = $this->createEntry();

        for ($i = 0; $i < 1000; ++$i) {
            self::assertIsArray(
                $entry->toArray(),
            );
        }
    }

    public function testItHandlesHugeIngestionWarningsWithoutCrash(): void
    {
        $warnings = [];

        for ($i = 0; $i < 5000; ++$i) {
            $warnings[] = new IngestionWarning(
                field: 'field-' . $i,
                type: IngestionWarningType::MESSAGE_TRUNCATED,
                original: str_repeat(
                    'payload',
                    50,
                ),
                fallback: 'truncated',
            );
        }

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        self::assertCount(
            5000,
            $entry->ingestionWarnings(),
        );
    }

    public function testItSerializesHugeWarningsWithoutCrash(): void
    {
        $warnings = [];

        for ($i = 0; $i < 1000; ++$i) {
            $warnings[] = new IngestionWarning(
                field: 'ip',
                type: IngestionWarningType::INVALID_IP,
                original: '999.999.999.999',
                fallback: '127.0.0.1',
            );
        }

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        $data = $entry->toArray();

        self::assertCount(
            1000,
            $data['ingestionWarnings'],
        );
    }

    public function testItRejectsInvalidWarnings(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            ingestionWarnings: [
                'invalid-warning',
            ],
        );
    }

    public function testItRejectsHugeMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: str_repeat(
                'A',
                1001,
            ),
        );
    }

    public function testItRejectsHugeDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: str_repeat(
                'billing',
                100,
            ),
        );
    }

    /**
     * @param list<IngestionWarning|mixed> $ingestionWarnings
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        string $message = 'Payment failed',
        string $domain = 'billing',
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: LogLevel::ERROR,
            domain: $domain,
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),
            client: new Client('checkout-app'),
            request: new Request(
                method: 'POST',
                uri: new Uri('/orders'),
                userAgent: 'Mozilla/5.0',
            ),
            ipAddress: new IpAddress('127.0.0.1'),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
            requestId: new RequestId(
                'req_crash_test',
            ),
            ingestionWarnings: $ingestionWarnings,
            context: $context,
            extra: $extra,
        );
    }
}