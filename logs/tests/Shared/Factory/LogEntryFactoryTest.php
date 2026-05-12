<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de LogEntryFactory.
 */
final class LogEntryFactoryTest extends TestCase
{
    public function testItCreatesValidLogEntry(): void
    {
        $entry = LogEntryFactory::create();

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertSame(
            'Test log entry',
            $entry->message(),
        );

        self::assertSame(
            'app',
            $entry->domain(),
        );

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertSame(
            Environment::Test,
            $entry->environment(),
        );

        self::assertSame(
            500,
            $entry->httpStatus()->value(),
        );

        self::assertSame(
            'phpunit',
            $entry->client()->value(),
        );

        self::assertSame(
            '/test',
            $entry->request()->uri()->value(),
        );

        self::assertSame(
            'GET',
            $entry->request()->method(),
        );

        self::assertSame(
            '127.0.0.1',
            $entry->ipAddress()->value(),
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $entry->fingerprint()->value(),
        );
    }

    public function testItCreatesCustomMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: 'Database failure',
        );

        self::assertSame(
            'Database failure',
            $entry->message(),
        );
    }

    public function testItCreatesErrorLog(): void
    {
        $entry = LogEntryFactory::error();

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    public function testItCreatesWarningLog(): void
    {
        $entry = LogEntryFactory::warning();

        self::assertSame(
            LogLevel::WARNING,
            $entry->level(),
        );
    }

    public function testItCreatesInfoLog(): void
    {
        $entry = LogEntryFactory::info();

        self::assertSame(
            LogLevel::INFO,
            $entry->level(),
        );
    }

    public function testItCreatesManyEntries(): void
    {
        $entries = LogEntryFactory::many(5);

        self::assertCount(
            5,
            $entries,
        );
    }

    public function testItCreatesUniqueIds(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        self::assertNotSame(
            $entry1->id(),
            $entry2->id(),
        );
    }

    public function testItCreatesUniqueFingerprints(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'message-1',
        );

        $entry2 = LogEntryFactory::create(
            message: 'message-2',
        );

        self::assertNotSame(
            $entry1->fingerprint()->value(),
            $entry2->fingerprint()->value(),
        );
    }

    public function testItUsesProvidedId(): void
    {
        $entry = LogEntryFactory::create(
            id: 'custom-id',
        );

        self::assertSame(
            'custom-id',
            $entry->id(),
        );
    }

    public function testItUsesProvidedFingerprint(): void
    {
        $entry = LogEntryFactory::create(
            fingerprint: 'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $entry->fingerprint()->value(),
        );
    }

    public function testItStoresContext(): void
    {
        $entry = LogEntryFactory::create(
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

    public function testItStoresExtra(): void
    {
        $entry = LogEntryFactory::create(
            extra: [
                'memory' => '128MB',
            ],
        );

        self::assertSame(
            [
                'memory' => '128MB',
            ],
            $entry->extra(),
        );
    }

    public function testItCreatesCustomEnvironment(): void
    {
        $entry = LogEntryFactory::create(
            environment: Environment::Production,
        );

        self::assertSame(
            Environment::Production,
            $entry->environment(),
        );
    }

    public function testItCreatesCustomDomain(): void
    {
        $entry = LogEntryFactory::create(
            domain: 'billing',
        );

        self::assertSame(
            'billing',
            $entry->domain(),
        );
    }

    public function testItCreatesCustomRequest(): void
    {
        $entry = LogEntryFactory::create(
            method: 'POST',
            uri: '/api/logs',
            userAgent: 'Symfony HttpClient',
        );

        self::assertSame(
            'POST',
            $entry->request()->method(),
        );

        self::assertSame(
            '/api/logs',
            $entry->request()->uri()->value(),
        );

        self::assertSame(
            'Symfony HttpClient',
            $entry->request()->userAgent(),
        );
    }

    public function testItCreatesCustomIp(): void
    {
        $entry = LogEntryFactory::create(
            ip: '192.168.1.10',
        );

        self::assertSame(
            '192.168.1.10',
            $entry->ipAddress()->value(),
        );
    }

    public function testManyReturnsEmptyArrayWhenNegative(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(-5),
        );
    }

    public function testManyReturnsEmptyArrayWhenZero(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(0),
        );
    }

    public function testManyCreatesRequestedAmount(): void
    {
        self::assertCount(
            250,
            LogEntryFactory::many(250),
        );
    }
}