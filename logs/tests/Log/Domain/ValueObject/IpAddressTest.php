<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidIpAddressException;
use App\Log\Domain\ValueObject\IpAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject IpAddress.
 *
 * Objectifs :
 * - garantir les invariants IP
 * - garantir les normalisations
 * - garantir les fallbacks ingestion
 * - verrouiller les helpers réseau
 * - garantir la robustesse face aux payloads hostiles
 */
final class IpAddressTest extends TestCase
{
    #[DataProvider('provideValidIpAddresses')]
    public function testItCreatesValidIpAddress(
        string $input,
        string $expected,
    ): void {
        $ip = new IpAddress($input);

        self::assertSame(
            $expected,
            $ip->value(),
        );
    }

    #[DataProvider('provideInvalidIpAddresses')]
    public function testItRejectsInvalidIpAddress(
        string $input,
    ): void {
        $this->expectException(
            InvalidIpAddressException::class,
        );

        new IpAddress($input);
    }

    public function testItCreatesFromExternalIpv4(): void
    {
        $ip = IpAddress::fromExternal(
            '192.168.1.10',
        );

        self::assertSame(
            '192.168.1.10',
            $ip->value(),
        );
    }

    public function testItCreatesFromExternalIpv6(): void
    {
        $ip = IpAddress::fromExternal(
            '2001:db8::1',
        );

        self::assertSame(
            '2001:db8::1',
            $ip->value(),
        );
    }

    public function testItFallsBackForInvalidString(): void
    {
        $ip = IpAddress::fromExternal(
            'not-an-ip',
        );

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    public function testItFallsBackForNull(): void
    {
        $ip = IpAddress::fromExternal(null);

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    public function testItFallsBackForInvalidType(): void
    {
        $ip = IpAddress::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    public function testItDetectsIpv4(): void
    {
        $ip = new IpAddress(
            '192.168.1.10',
        );

        self::assertTrue(
            $ip->isV4(),
        );

        self::assertFalse(
            $ip->isV6(),
        );
    }

    public function testItDetectsIpv6(): void
    {
        $ip = new IpAddress(
            '2001:db8::1',
        );

        self::assertTrue(
            $ip->isV6(),
        );

        self::assertFalse(
            $ip->isV4(),
        );
    }

    public function testItDetectsLocalIpv4(): void
    {
        $ip = new IpAddress(
            '127.0.0.1',
        );

        self::assertTrue(
            $ip->isLocal(),
        );
    }

    public function testItDetectsLocalIpv6(): void
    {
        $ip = new IpAddress(
            '::1',
        );

        self::assertTrue(
            $ip->isLocal(),
        );
    }

    public function testItDetectsNonLocalIp(): void
    {
        $ip = new IpAddress(
            '8.8.8.8',
        );

        self::assertFalse(
            $ip->isLocal(),
        );
    }

    public function testItDetectsPrivateIp(): void
    {
        $ip = new IpAddress(
            '192.168.1.10',
        );

        self::assertTrue(
            $ip->isPrivate(),
        );

        self::assertFalse(
            $ip->isPublic(),
        );
    }

    public function testItDetectsPublicIp(): void
    {
        $ip = new IpAddress(
            '8.8.8.8',
        );

        self::assertTrue(
            $ip->isPublic(),
        );

        self::assertFalse(
            $ip->isPrivate(),
        );
    }

    public function testItComparesIpAddresses(): void
    {
        $left = new IpAddress('8.8.8.8');
        $right = new IpAddress('8.8.8.8');
        $other = new IpAddress('1.1.1.1');

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $ip = new IpAddress(
            '8.8.8.8',
        );

        self::assertSame(
            '8.8.8.8',
            (string) $ip,
        );
    }

    public function testItNormalizesTrimAndCase(): void
    {
        $ip = new IpAddress(
            '   ::1   ',
        );

        self::assertSame(
            '::1',
            $ip->value(),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un IpAddress valide
     */
    public function testItNeverThrowsFromExternal(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            '',
            true,
            false,
            [],
            ['127.0.0.1'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '999.999.999.999',
            '::::',
        ];

        foreach ($inputs as $input) {
            $ip = IpAddress::fromExternal($input);

            self::assertInstanceOf(
                IpAddress::class,
                $ip,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidIpAddress(): void
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
            $ip = IpAddress::fromExternal($input);

            self::assertNotSame(
                '',
                $ip->value(),
            );

            self::assertTrue(
                filter_var(
                    $ip->value(),
                    FILTER_VALIDATE_IP,
                ) !== false,
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
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
            "'; DROP TABLE logs; --",
        ];

        foreach ($inputs as $input) {
            $ip = IpAddress::fromExternal($input);

            self::assertSame(
                '0.0.0.0',
                $ip->value(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('A', 1000000);

        $ip = IpAddress::fromExternal($payload);

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $ip = IpAddress::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            IpAddress::class,
            $ip,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $ip = IpAddress::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            IpAddress::class,
            $ip,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidIpAddresses(): iterable
    {
        yield 'ipv4 public' => [
            '8.8.8.8',
            '8.8.8.8',
        ];

        yield 'ipv4 private' => [
            '192.168.1.10',
            '192.168.1.10',
        ];

        yield 'ipv4 localhost' => [
            '127.0.0.1',
            '127.0.0.1',
        ];

        yield 'ipv6' => [
            '2001:db8::1',
            '2001:db8::1',
        ];

        yield 'ipv6 localhost' => [
            '::1',
            '::1',
        ];

        yield 'trimmed ipv6' => [
            '   ::1   ',
            '::1',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidIpAddresses(): iterable
    {
        yield 'empty string' => [''];

        yield 'random string' => ['hello'];

        yield 'invalid ipv4' => ['999.999.999.999'];

        yield 'invalid ipv6' => ['::::'];

        yield 'hostname' => ['localhost'];

        yield 'url' => ['https://example.com'];

        yield 'malicious payload' => [
            '<script>alert(1)</script>',
        ];
    }
}