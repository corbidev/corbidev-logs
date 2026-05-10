<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidClientException;
use App\Log\Domain\ValueObject\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Client.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les normalisations
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class ClientTest extends TestCase
{
    #[DataProvider('provideValidClients')]
    public function testItCreatesValidClient(
        string $input,
        string $expected,
    ): void {
        $client = new Client($input);

        self::assertSame(
            $expected,
            $client->value(),
        );
    }

    #[DataProvider('provideInvalidClients')]
    public function testItRejectsInvalidClient(
        string $input,
    ): void {
        $this->expectException(
            InvalidClientException::class,
        );

        new Client($input);
    }

    public function testItCreatesFromExternalString(): void
    {
        $client = Client::fromExternal(
            'checkout-service',
        );

        self::assertSame(
            'checkout-service',
            $client->value(),
        );
    }

    public function testItNormalizesCase(): void
    {
        $client = new Client(
            'CHECKOUT_SERVICE',
        );

        self::assertSame(
            'checkout_service',
            $client->value(),
        );
    }

    public function testItNormalizesSpaces(): void
    {
        $client = new Client(
            'My Awesome App',
        );

        self::assertSame(
            'my-awesome-app',
            $client->value(),
        );
    }

    public function testItNormalizesTrim(): void
    {
        $client = new Client(
            '   Checkout-App   ',
        );

        self::assertSame(
            'checkout-app',
            $client->value(),
        );
    }

    public function testItFallsBackForInvalidString(): void
    {
        $client = Client::fromExternal(
            '<script>alert(1)</script>',
        );

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    public function testItFallsBackForNull(): void
    {
        $client = Client::fromExternal(null);

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    public function testItFallsBackForInvalidType(): void
    {
        $client = Client::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    public function testItDetectsUnknownClient(): void
    {
        $client = Client::fromExternal(
            '<script>',
        );

        self::assertTrue(
            $client->isUnknown(),
        );
    }

    public function testItDetectsKnownClient(): void
    {
        $client = new Client(
            'checkout-service',
        );

        self::assertFalse(
            $client->isUnknown(),
        );
    }

    public function testItComparesClients(): void
    {
        $left = new Client('checkout');
        $right = new Client('checkout');
        $other = new Client('billing');

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $client = new Client(
            'checkout-service',
        );

        self::assertSame(
            'checkout-service',
            (string) $client,
        );
    }

    public function testItSupportsMaximumLengthBoundary(): void
    {
        $value = str_repeat('a', 100);

        $client = new Client($value);

        self::assertSame(
            $value,
            $client->value(),
        );
    }

    public function testItRejectsTooLongClient(): void
    {
        $this->expectException(
            InvalidClientException::class,
        );

        new Client(
            str_repeat('a', 101),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Client valide
     */
    public function testItNeverThrowsFromExternal(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            '',
            '   ',
            true,
            false,
            0,
            1,
            [],
            ['client'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '../../../../../etc/passwd',
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $client = Client::fromExternal($input);

            self::assertInstanceOf(
                Client::class,
                $client,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidClient(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            true,
            false,
            [],
            new stdClass(),
            123,
            999999,
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $client = Client::fromExternal($input);

            self::assertNotSame(
                '',
                $client->value(),
            );

            self::assertLessThanOrEqual(
                100,
                mb_strlen($client->value()),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testItFallsBackForHostilePayloads(): void
    {
        $inputs = [
            null,
            '',
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
            "'; DROP TABLE logs; --",
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $client = Client::fromExternal($input);

            self::assertSame(
                'unknown',
                $client->value(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('CLIENT', 100000);

        $client = Client::fromExternal($payload);

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $client = Client::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Client::class,
            $client,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $client = Client::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Client::class,
            $client,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidClients(): iterable
    {
        yield 'simple' => [
            'checkout',
            'checkout',
        ];

        yield 'dash separated' => [
            'checkout-service',
            'checkout-service',
        ];

        yield 'underscore separated' => [
            'CHECKOUT_SERVICE',
            'checkout_service',
        ];

        yield 'dot separated' => [
            'api.gateway.v2',
            'api.gateway.v2',
        ];

        yield 'spaces normalized' => [
            'My Awesome App',
            'my-awesome-app',
        ];

        yield 'trimmed' => [
            '   Billing-App   ',
            'billing-app',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidClients(): iterable
    {
        yield 'empty string' => [''];

        yield 'spaces only' => ['   '];

        yield 'invalid characters' => [
            '<script>',
        ];

        yield 'emoji' => [
            '🔥app',
        ];

        yield 'slash' => [
            'app/backend',
        ];

        yield 'too long' => [
            str_repeat('a', 101),
        ];
    }
}