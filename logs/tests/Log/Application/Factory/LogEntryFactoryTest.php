<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Factory;

use App\Log\Application\Factory\LogEntryFactory;
use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogEntryFactory.
 */
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
            'uri' => '/orders',
            'method' => 'POST',
            'ip' => '127.0.0.1',
            'fingerprint' => 'abcdef1234567890',
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
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
    }

    public function testItCreatesRequest(): void
    {
        $entry = $this->factory->create([
            'uri' => '/orders',
            'method' => 'POST',
        ]);

        self::assertSame(
            '/orders',
            $entry->request()->uri()->value(),
        );

        self::assertSame(
            'POST',
            $entry->request()->method(),
        );
    }

    public function testItFallsBackRequestMethod(): void
    {
        $entry = $this->factory->create([
            'method' => null,
        ]);

        self::assertSame(
            'GET',
            $entry->request()->method(),
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
}