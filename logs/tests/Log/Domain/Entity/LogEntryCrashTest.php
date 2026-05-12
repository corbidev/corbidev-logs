<?php

declare(strict_types=1);

namespace App\Tests\Crash\Log\Domain\Entity;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests critiques de LogEntry.
 *
 * Objectifs :
 * - garantir absence de crash
 * - garantir robustesse mémoire
 * - garantir stabilité domaine
 * - tester payloads hostiles
 *
 * IMPORTANT :
 * ------------
 * Les tags ont été supprimés du domaine.
 *
 * Les crash tests doivent désormais :
 * - rester focalisés sur LogEntry
 * - rester déterministes
 * - rester bornés
 * - ne contenir aucune logique legacy
 */
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

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
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

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertCount(
            10000,
            $entry->extra(),
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
        $payload = hex2bin('b131');

        self::assertNotFalse($payload);

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

    public function testItHandlesHostilePayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'xss' => '<script>alert(1)</script>',
                'sql' => "'; DROP TABLE logs; --",
                'path' => '../../../../../etc/passwd',
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        array $context = [],
        array $extra = [],
    ): LogEntry {
        return new LogEntry(
            message: 'Payment failed',
            level: LogLevel::ERROR,
            domain: 'billing',
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),
            client: new Client('checkout-app'),
            request: new Request(
                new Uri('/orders'),
                'POST',
                'Mozilla/5.0',
            ),
            ipAddress: new IpAddress('127.0.0.1'),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
            context: $context,
            extra: $extra,
        );
    }
}