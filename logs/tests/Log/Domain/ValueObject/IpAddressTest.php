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
    /**
     * But : Vérifier que IpAddress accepte des adresses valides et les normalise correctement.
     *
     * Entrée : Cas fournis par le DataProvider `provideValidIpAddresses()`
     * Résultat attendu : La valeur normalisée correspond à l'attendu du DataProvider
     */
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
    /**
     * But : Vérifier que IpAddress rejette les adresses invalides.
     *
     * Entrée : Cas fournis par le DataProvider `provideInvalidIpAddresses()`
     * Résultat attendu : InvalidIpAddressException est levée
     */
    public function testItRejectsInvalidIpAddress(
        string $input,
    ): void {
        $this->expectException(
            InvalidIpAddressException::class,
        );

        new IpAddress($input);
    }

    /**
     * But : Vérifier que fromExternal() crée une IpAddress depuis une IPv4 valide.
     *
     * Entrée : '192.168.1.10'
     * Résultat attendu : IpAddress avec valeur '192.168.1.10'
     */
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

    /**
     * But : Vérifier que fromExternal() crée une IpAddress depuis une IPv6 valide.
     *
     * Entrée : '2001:db8::1'
     * Résultat attendu : IpAddress avec valeur '2001:db8::1'
     */
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

    /**
     * But : Vérifier que fromExternal() retourne '0.0.0.0' pour une string invalide.
     *
     * Entrée : 'not-an-ip'
     * Résultat attendu : '0.0.0.0'
     */
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

    /**
     * But : Vérifier que fromExternal() retourne '0.0.0.0' pour null.
     *
     * Entrée : null
     * Résultat attendu : '0.0.0.0'
     */
    public function testItFallsBackForNull(): void
    {
        $ip = IpAddress::fromExternal(null);

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '0.0.0.0' pour un type invalide.
     *
     * Entrée : ['invalid'] (tableau)
     * Résultat attendu : '0.0.0.0'
     */
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

    /**
     * But : Vérifier que isV4() retourne true et isV6() false pour une adresse IPv4.
     *
     * Entrée : '192.168.1.10'
     * Résultat attendu : isV4() = true, isV6() = false
     */
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

    /**
     * But : Vérifier que isV6() retourne true et isV4() false pour une adresse IPv6.
     *
     * Entrée : '::1'
     * Résultat attendu : isV6() = true, isV4() = false
     */
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

    /**
     * But : Vérifier que isLocal() retourne true pour l'adresse de loopback IPv4.
     *
     * Entrée : '127.0.0.1'
     * Résultat attendu : isLocal() = true
     */
    public function testItDetectsLocalIpv4(): void
    {
        $ip = new IpAddress(
            '127.0.0.1',
        );

        self::assertTrue(
            $ip->isLocal(),
        );
    }

    /**
     * But : Vérifier que isLocal() retourne true pour l'adresse de loopback IPv6.
     *
     * Entrée : '::1'
     * Résultat attendu : isLocal() = true
     */
    public function testItDetectsLocalIpv6(): void
    {
        $ip = new IpAddress(
            '::1',
        );

        self::assertTrue(
            $ip->isLocal(),
        );
    }

    /**
     * But : Vérifier que isLocal() retourne false pour une adresse publique.
     *
     * Entrée : '8.8.8.8'
     * Résultat attendu : isLocal() = false
     */
    public function testItDetectsNonLocalIp(): void
    {
        $ip = new IpAddress(
            '8.8.8.8',
        );

        self::assertFalse(
            $ip->isLocal(),
        );
    }

    /**
     * But : Vérifier que isPrivate() retourne true et isPublic() false pour une IP privée.
     *
     * Entrée : '192.168.1.10'
     * Résultat attendu : isPrivate() = true, isPublic() = false
     */
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

    /**
     * But : Vérifier que isPublic() retourne true et isPrivate() false pour une IP publique.
     *
     * Entrée : '8.8.8.8'
     * Résultat attendu : isPublic() = true, isPrivate() = false
     */
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

    /**
     * But : Vérifier que equals() compare correctement deux IpAddress.
     *
     * Entrée : IpAddress('8.8.8.8') vs IpAddress('8.8.8.8'), puis vs IpAddress('1.1.1.1')
     * Résultat attendu : equals() = true / false
     */
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

    /**
     * But : Vérifier que la conversion en string retourne la valeur de l'IP.
     *
     * Entrée : IpAddress('8.8.8.8')
     * Résultat attendu : (string) IpAddress = '8.8.8.8'
     */
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

    /**
     * But : Vérifier que l'adresse IP est normalisée (trim et casse).
     *
     * Entrée : '   ::1   '
     * Résultat attendu : '::1'
     */
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
    /**
     * But : Vérifier que fromExternal() ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 15 inputs hostiles variés
     * Résultat attendu : Aucune exception levée
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

    /**
     * But : Vérifier que fromExternal() retourne toujours une instance d'IpAddress valide.
     *
     * Entrée : 12 inputs variés
     * Résultat attendu : Instance IpAddress valide à chaque appel
     */
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

    /**
     * But : Vérifier que fromExternal() retourne '0.0.0.0' pour des inputs hostiles.
     *
     * Entrée : 7 inputs hostiles (null, [], stdClass, binaire, UTF-8 invalide, XSS, SQL injection)
     * Résultat attendu : '0.0.0.0' pour chaque input
     */
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

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec un payload de 1 000 000 caractères.
     *
     * Entrée : str_repeat('A', 1000000)
     * Résultat attendu : '0.0.0.0' (fallback), aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('A', 1000000);

        $ip = IpAddress::fromExternal($payload);

        self::assertSame(
            '0.0.0.0',
            $ip->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() gère une séquence binaire sans exception.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Instance IpAddress créée (fallback ou valide)
     */
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

    /**
     * But : Vérifier que fromExternal() gère une valeur UTF-8 invalide sans exception.
     *
     * Entrée : hex2bin('b131')
     * Résultat attendu : Instance IpAddress créée (fallback ou valide)
     */
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