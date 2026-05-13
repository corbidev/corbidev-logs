<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\Entity;

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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests métier de LogEntry.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - immutabilité
 * - cohérence métier
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - invariants métier
 * - normalisation
 * - requestId obligatoire
 * - stabilité serialization
 * - stabilité equals()
 * - stabilité ingestionWarnings
 * - cohérence ValueObjects
 */
#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    public function testItCreatesValidLogEntry(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );

        self::assertSame(
            'billing',
            $entry->domain(),
        );

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertSame(
            Environment::Production,
            $entry->environment(),
        );
    }

    public function testItNormalizesDomain(): void
    {
        $entry = $this->createEntry(
            domain: ' BILLING ',
        );

        self::assertSame(
            'billing',
            $entry->domain(),
        );
    }

    public function testItNormalizesMessage(): void
    {
        $entry = $this->createEntry(
            message: '  Payment failed  ',
        );

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );
    }

    public function testItRejectsEmptyMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: '',
        );
    }

    public function testItRejectsWhitespaceMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: '      ',
        );
    }

    public function testItRejectsEmptyDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: '',
        );
    }

    public function testItRejectsWhitespaceDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: '      ',
        );
    }

    public function testItRejectsTooLongMessage(): void
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

    public function testItRejectsTooLongDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: str_repeat(
                'a',
                101,
            ),
        );
    }

    public function testItReturnsStableId(): void
    {
        $entry = $this->createEntry();

        self::assertNotEmpty(
            $entry->id(),
        );
    }

    public function testItGeneratesDifferentIds(): void
    {
        $left = $this->createEntry();
        $right = $this->createEntry();

        self::assertNotSame(
            $left->id(),
            $right->id(),
        );
    }

    public function testItUsesProvidedId(): void
    {
        $entry = $this->createEntry(
            id: 'external-id',
        );

        self::assertSame(
            'external-id',
            $entry->id(),
        );
    }

    public function testItReturnsStableSerialization(): void
    {
        $entry = $this->createEntry();

        $data = $entry->toArray();

        self::assertArrayHasKey(
            'id',
            $data,
        );

        self::assertArrayHasKey(
            'message',
            $data,
        );

        self::assertArrayHasKey(
            'fingerprint',
            $data,
        );

        self::assertArrayHasKey(
            'ingestionWarnings',
            $data,
        );
    }

    public function testItReturnsStableRequestSerialization(): void
    {
        $entry = $this->createEntry();

        $request = $entry->toArray()['request'];

        self::assertSame(
            'POST',
            $request['method'],
        );

        self::assertSame(
            '/checkout',
            $request['uri'],
        );

        self::assertSame(
            'PHPUnit',
            $request['userAgent'],
        );
    }

    public function testItDetectsErrorLog(): void
    {
        $entry = $this->createEntry(
            level: LogLevel::ERROR,
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    public function testItDetectsHttpError(): void
    {
        $entry = $this->createEntry(
            httpStatus: new HttpStatus(
                500,
            ),
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    public function testItComparesEntriesById(): void
    {
        $entry = $this->createEntry(
            id: 'same-id',
        );

        $same = $this->createEntry(
            id: 'same-id',
        );

        self::assertTrue(
            $entry->equals(
                $same,
            ),
        );
    }

    public function testItDetectsDifferentEntries(): void
    {
        $left = $this->createEntry(
            id: 'left',
        );

        $right = $this->createEntry(
            id: 'right',
        );

        self::assertFalse(
            $left->equals(
                $right,
            ),
        );
    }

    public function testItStoresContext(): void
    {
        $entry = $this->createEntry(
            context: [
                'userId' => 42,
            ],
        );

        self::assertSame(
            42,
            $entry
                ->context()['userId'],
        );
    }

    public function testItStoresExtra(): void
    {
        $entry = $this->createEntry(
            extra: [
                'memory' => '128MB',
            ],
        );

        self::assertSame(
            '128MB',
            $entry
                ->extra()['memory'],
        );
    }

    public function testItStoresClientDate(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            clientDate: $date,
        );

        self::assertSame(
            $date,
            $entry->clientDate(),
        );
    }

    public function testItStoresIngestionWarnings(): void
    {
        $warnings = [
            new IngestionWarning(
                field: 'ip',
                type: IngestionWarningType::INVALID_IP,
                original: '999.999.999.999',
                fallback: '127.0.0.1',
            ),
        ];

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        self::assertCount(
            1,
            $entry->ingestionWarnings(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );
    }

    public function testItReturnsFalseWithoutWarnings(): void
    {
        $entry = $this->createEntry();

        self::assertFalse(
            $entry->hasIngestionWarnings(),
        );
    }

    public function testItRejectsInvalidWarnings(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            ingestionWarnings: [
                'invalid',
            ],
        );
    }

    /**
     * @param list<IngestionWarning|mixed> $ingestionWarnings
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        string $message = 'Payment failed',
        LogLevel $level = LogLevel::ERROR,
        string $domain = 'billing',
        Environment $environment = Environment::Production,
        ?HttpStatus $httpStatus = null,
        ?Client $client = null,
        ?Request $request = null,
        ?IpAddress $ipAddress = null,
        ?Fingerprint $fingerprint = null,
        ?RequestId $requestId = null,
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null,
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: $level,
            domain: $domain,
            environment: $environment,
            httpStatus: $httpStatus
                ?? new HttpStatus(500),
            client: $client
                ?? new Client('phpunit'),
            request: $request
                ?? new Request(
                    method: 'POST',
                    uri: new Uri('/checkout'),
                    userAgent: 'PHPUnit',
                ),
            ipAddress: $ipAddress
                ?? new IpAddress('127.0.0.1'),
            fingerprint: $fingerprint
                ?? new Fingerprint('abcdef1234567890'),
            requestId: $requestId
                ?? new RequestId('req_checkout'),
            ingestionWarnings: $ingestionWarnings,
            context: $context,
            extra: $extra,
            clientDate: $clientDate,
            createdAt: $createdAt,
            id: $id,
        );
    }
}