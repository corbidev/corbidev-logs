<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidFingerprintException;
use App\Log\Domain\ValueObject\Fingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Fingerprint.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir le format fingerprint
 * - garantir la stabilité des normalisations
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class FingerprintTest extends TestCase
{
    #[DataProvider('provideValidFingerprints')]
    public function testItCreatesValidFingerprint(
        string $input,
        string $expected,
    ): void {
        $fingerprint = new Fingerprint($input);

        self::assertSame(
            $expected,
            $fingerprint->value(),
        );
    }

    #[DataProvider('provideInvalidFingerprints')]
    public function testItRejectsInvalidFingerprint(
        string $input,
    ): void {
        $this->expectException(
            InvalidFingerprintException::class,
        );

        new Fingerprint($input);
    }

    public function testItCreatesFromExternalString(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    public function testItNormalizesCase(): void
    {
        $fingerprint = new Fingerprint(
            'ABCDEF1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    public function testItNormalizesTrim(): void
    {
        $fingerprint = new Fingerprint(
            '   abcdef1234567890   ',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    public function testItFallsBackForInvalidString(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            '<script>alert(1)</script>',
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    public function testItFallsBackForNull(): void
    {
        $fingerprint = Fingerprint::fromExternal(null);

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    public function testItFallsBackForInvalidType(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    public function testItDetectsFallbackFingerprint(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            '<script>',
        );

        self::assertTrue(
            $fingerprint->isFallback(),
        );
    }

    public function testItDetectsNonFallbackFingerprint(): void
    {
        $fingerprint = new Fingerprint(
            'abcdef1234567890',
        );

        self::assertFalse(
            $fingerprint->isFallback(),
        );
    }

    public function testItComparesFingerprints(): void
    {
        $left = new Fingerprint(
            'abcdef1234567890',
        );

        $right = new Fingerprint(
            'abcdef1234567890',
        );

        $other = new Fingerprint(
            '1234567890abcdef',
        );

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $fingerprint = new Fingerprint(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            (string) $fingerprint,
        );
    }

    public function testItGeneratesStableFingerprint(): void
    {
        $left = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        $right = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        self::assertTrue(
            $left->equals($right),
        );
    }

    public function testItGeneratesDifferentFingerprint(): void
    {
        $left = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        $right = Fingerprint::generate(
            'error|404|billing|/orders|prod',
        );

        self::assertFalse(
            $left->equals($right),
        );
    }

    public function testGeneratedFingerprintHasExpectedLength(): void
    {
        $fingerprint = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        self::assertSame(
            16,
            mb_strlen(
                $fingerprint->value(),
            ),
        );
    }

    public function testGeneratedFingerprintIsLowercase(): void
    {
        $fingerprint = Fingerprint::generate(
            'ERROR|500|BILLING|/ORDERS|PROD',
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $fingerprint->value(),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Fingerprint valide
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
            ['fingerprint'],
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
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertInstanceOf(
                Fingerprint::class,
                $fingerprint,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidFingerprint(): void
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
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertSame(
                16,
                mb_strlen(
                    $fingerprint->value(),
                ),
            );

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{16}$/',
                $fingerprint->value(),
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
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertSame(
                '0000000000000000',
                $fingerprint->value(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'A',
            1000000,
        );

        $fingerprint = Fingerprint::fromExternal(
            $payload,
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Fingerprint::class,
            $fingerprint,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Fingerprint::class,
            $fingerprint,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidFingerprints(): iterable
    {
        yield 'lowercase' => [
            'abcdef1234567890',
            'abcdef1234567890',
        ];

        yield 'uppercase normalized' => [
            'ABCDEF1234567890',
            'abcdef1234567890',
        ];

        yield 'trimmed' => [
            '   abcdef1234567890   ',
            'abcdef1234567890',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidFingerprints(): iterable
    {
        yield 'empty string' => [''];

        yield 'too short' => [
            'abcdef',
        ];

        yield 'too long' => [
            'abcdef1234567890aaaa',
        ];

        yield 'invalid characters' => [
            'abcdef12345678$$',
        ];

        yield 'emoji' => [
            '🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥',
        ];

        yield 'slash' => [
            'abcd/efgh1234567',
        ];
    }
}