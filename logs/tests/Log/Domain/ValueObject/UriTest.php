<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidUriException;
use App\Log\Domain\ValueObject\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Uri.
 *
 * Objectifs :
 * - garantir la stabilité des normalisations
 * - garantir les invariants métier
 * - garantir la suppression des query strings
 * - verrouiller les comportements hostiles
 * - garantir la robustesse ingestion
 */
final class UriTest extends TestCase
{
    #[DataProvider('provideValidUris')]
    public function testItCreatesValidUri(
        string $input,
        string $expected,
    ): void {
        $uri = new Uri($input);

        self::assertSame(
            $expected,
            $uri->value(),
        );
    }

    #[DataProvider('provideInvalidUris')]
    public function testItRejectsInvalidUri(
        string $input,
    ): void {
        $this->expectException(
            InvalidUriException::class,
        );

        new Uri($input);
    }

    public function testItCreatesUriFromExternalString(): void
    {
        $uri = Uri::fromExternal('/orders');

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    public function testItFallsBackToRootForNull(): void
    {
        $uri = Uri::fromExternal(null);

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    public function testItFallsBackToRootForInvalidType(): void
    {
        $uri = Uri::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    public function testItFallsBackToRootForInvalidUri(): void
    {
        $uri = Uri::fromExternal(
            str_repeat('a', 3000),
        );

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    public function testItRemovesQueryString(): void
    {
        $uri = new Uri(
            '/orders?id=42&token=secret',
        );

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    public function testItRemovesFragment(): void
    {
        $uri = new Uri(
            '/orders#create',
        );

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    public function testItNormalizesDuplicateSlashes(): void
    {
        $uri = new Uri(
            '///orders///create///',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    public function testItNormalizesFullUrl(): void
    {
        $uri = new Uri(
            'https://example.com/orders/create?id=42',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    public function testItNormalizesMissingLeadingSlash(): void
    {
        $uri = new Uri(
            'orders/create',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    public function testItNormalizesEmptyStringToRoot(): void
    {
        $uri = new Uri('');

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    public function testItDetectsRootUri(): void
    {
        $uri = new Uri('/');

        self::assertTrue(
            $uri->isRoot(),
        );

        self::assertFalse(
            $uri->hasSegments(),
        );
    }

    public function testItDetectsUriWithSegments(): void
    {
        $uri = new Uri('/orders/create');

        self::assertFalse(
            $uri->isRoot(),
        );

        self::assertTrue(
            $uri->hasSegments(),
        );
    }

    public function testItReturnsSegments(): void
    {
        $uri = new Uri('/orders/create');

        self::assertSame(
            [
                'orders',
                'create',
            ],
            $uri->segments(),
        );
    }

    public function testItReturnsEmptySegmentsForRoot(): void
    {
        $uri = new Uri('/');

        self::assertSame(
            [],
            $uri->segments(),
        );
    }

    public function testItComparesUris(): void
    {
        $left = new Uri('/orders');
        $right = new Uri('/orders');
        $other = new Uri('/users');

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $uri = new Uri('/orders');

        self::assertSame(
            '/orders',
            (string) $uri,
        );
    }

    public function testItDecodesEncodedCharacters(): void
    {
        $uri = new Uri(
            '/search/%E2%82%AC',
        );

        self::assertSame(
            '/search/€',
            $uri->value(),
        );
    }

    public function testItSupportsMaximumLengthBoundary(): void
    {
        $path = '/' . str_repeat('a', 2047);

        $uri = new Uri($path);

        self::assertSame(
            $path,
            $uri->value(),
        );
    }

    public function testItRejectsUriExceedingMaximumLength(): void
    {
        $this->expectException(
            InvalidUriException::class,
        );

        new Uri(
            '/' . str_repeat('a', 2048),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Uri valide
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
            ['uri'],
            new stdClass(),
            $resource,
            str_repeat('/test', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '../../../../../etc/passwd',
            'https://example.com/orders?id=42',
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertInstanceOf(
                Uri::class,
                $uri,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidUri(): void
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
            str_repeat('/test', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertNotSame(
                '',
                $uri->value(),
            );

            self::assertTrue(
                str_starts_with(
                    $uri->value(),
                    '/',
                ),
            );

            self::assertLessThanOrEqual(
                2048,
                mb_strlen($uri->value()),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testItFallsBackToRootForHostilePayloads(): void
    {
        $inputs = [
            null,
            [],
            new stdClass(),
            str_repeat('a', 500000),
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertSame(
                '/',
                $uri->value(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = '/' . str_repeat('a', 1000000);

        $uri = Uri::fromExternal($payload);

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $uri = Uri::fromExternal(
            "/test\x00\x01\x02",
        );

        self::assertInstanceOf(
            Uri::class,
            $uri,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $uri = Uri::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Uri::class,
            $uri,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidUris(): iterable
    {
        yield 'root' => [
            '/',
            '/',
        ];

        yield 'simple path' => [
            '/orders',
            '/orders',
        ];

        yield 'nested path' => [
            '/orders/create',
            '/orders/create',
        ];

        yield 'trimmed path' => [
            '   /orders/create   ',
            '/orders/create',
        ];

        yield 'duplicate slashes' => [
            '///orders///create///',
            '/orders/create',
        ];

        yield 'full url' => [
            'https://example.com/orders?id=42',
            '/orders',
        ];

        yield 'without leading slash' => [
            'orders',
            '/orders',
        ];

        yield 'query string removed' => [
            '/orders?id=42',
            '/orders',
        ];

        yield 'fragment removed' => [
            '/orders#create',
            '/orders',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidUris(): iterable
    {
        yield 'too long' => [
            '/' . str_repeat('a', 3000),
        ];
    }
}