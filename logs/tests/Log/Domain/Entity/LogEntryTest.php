<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\Entity;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\Exception\InvalidLogEntryException;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Tags;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests métier standards de LogEntry.
 */
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

    public function testItRejectsEmptyDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: '',
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

    public function testItReturnsStableId(): void
    {
        $entry = $this->createEntry();

        self::assertNotEmpty(
            $entry->id(),
        );
    }

    public function testItReturnsHttpStatus(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            500,
            $entry->httpStatus()->value(),
        );
    }

    public function testItReturnsClient(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            'checkout-app',
            $entry->client()->value(),
        );
    }

    public function testItReturnsRequest(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            '/orders',
            $entry->request()->uri()->value(),
        );
    }

    public function testItReturnsIpAddress(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            '127.0.0.1',
            $entry->ipAddress()->value(),
        );
    }

    public function testItReturnsFingerprint(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            'abcdef1234567890',
            $entry->fingerprint()->value(),
        );
    }

    public function testItReturnsTags(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            'checkout',
            $entry->tags()->get(
                'feature',
            ),
        );
    }

    public function testItReturnsContext(): void
    {
        $entry = $this->createEntry(
            context: [
                'userId' => 42,
            ],
        );

        self::assertSame(
            [
                'userId' => 42,
            ],
            $entry->context(),
        );
    }

    public function testItReturnsExtra(): void
    {
        $entry = $this->createEntry(
            extra: [
                'memory' => 123,
            ],
        );

        self::assertSame(
            [
                'memory' => 123,
            ],
            $entry->extra(),
        );
    }

    public function testItReturnsCreatedAt(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            createdAt: $date,
        );

        self::assertSame(
            $date,
            $entry->createdAt(),
        );
    }

    public function testItReturnsClientDate(): void
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

    public function testItDetectsErrorLogLevel(): void
    {
        $entry = $this->createEntry();

        self::assertTrue(
            $entry->isError(),
        );
    }

    public function testItComparesEntries(): void
    {
        $id = '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21';

        $left = $this->createEntry(
            id: $id,
        );

        $right = $this->createEntry(
            id: $id,
        );

        $other = $this->createEntry();

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItSerializesToArray(): void
    {
        $entry = $this->createEntry();

        $data = $entry->toArray();

        self::assertSame(
            'Payment failed',
            $data['message'],
        );

        self::assertSame(
            'error',
            $data['level'],
        );

        self::assertSame(
            'billing',
            $data['domain'],
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        string $message = 'Payment failed',
        string $domain = 'billing',
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null,
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: LogLevel::ERROR,
            domain: $domain,
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
            tags: new Tags([
                'feature' => 'checkout',
            ]),
            context: $context,
            extra: $extra,
            clientDate: $clientDate,
            createdAt: $createdAt,
            id: $id,
        );
    }
}