<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidRequestException;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Request.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les normalisations HTTP
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class RequestTest extends TestCase
{
    public function testItCreatesValidRequest(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'POST',
            'Mozilla/5.0',
        );

        self::assertSame(
            '/orders',
            $request->uri()->value(),
        );

        self::assertSame(
            'POST',
            $request->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    public function testItNormalizesMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            ' post ',
        );

        self::assertSame(
            'POST',
            $request->method(),
        );
    }

    public function testItNormalizesUserAgent(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'GET',
            '  Mozilla/5.0  ',
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    public function testItRejectsInvalidMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            'INVALID',
        );
    }

    public function testItRejectsEmptyMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            '',
        );
    }

    public function testItRejectsTooLongMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            str_repeat('A', 20),
        );
    }

    public function testItRejectsTooLongUserAgent(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            'GET',
            str_repeat('A', 501),
        );
    }

    public function testItCreatesFromExternal(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'post',
            'Mozilla/5.0',
        );

        self::assertSame(
            '/orders',
            $request->uri()->value(),
        );

        self::assertSame(
            'POST',
            $request->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    public function testItFallsBackForInvalidMethod(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'INVALID',
        );

        self::assertSame(
            'GET',
            $request->method(),
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );
    }

    public function testItFallsBackForInvalidUri(): void
    {
        $request = Request::fromExternal(
            str_repeat('/', 5000),
            'POST',
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );
    }

    public function testItFallsBackForInvalidUserAgent(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'GET',
            str_repeat('A', 5000),
        );

        self::assertSame(
            '',
            $request->userAgent(),
        );
    }

    public function testItFallsBackForInvalidTypes(): void
    {
        $request = Request::fromExternal(
            [],
            new stdClass(),
            fopen('php://memory', 'r'),
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );

        self::assertSame(
            'GET',
            $request->method(),
        );

        self::assertSame(
            '',
            $request->userAgent(),
        );
    }

    public function testItDetectsGetMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'GET',
        );

        self::assertTrue(
            $request->isGet(),
        );

        self::assertFalse(
            $request->isPost(),
        );
    }

    public function testItDetectsPostMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'POST',
        );

        self::assertTrue(
            $request->isPost(),
        );

        self::assertFalse(
            $request->isGet(),
        );
    }

    public function testItComparesRequests(): void
    {
        $left = new Request(
            new Uri('/orders'),
            'POST',
            'UA',
        );

        $right = new Request(
            new Uri('/orders'),
            'POST',
            'UA',
        );

        $other = new Request(
            new Uri('/users'),
            'GET',
            'UA',
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
        $request = new Request(
            new Uri('/orders'),
            'POST',
        );

        self::assertSame(
            'POST /orders',
            (string) $request,
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Request valide
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
            123,
            999999,
            [],
            ['request'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $request = Request::fromExternal(
                $input,
                $input,
                $input,
            );

            self::assertInstanceOf(
                Request::class,
                $request,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidRequest(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'POST',
            'Mozilla',
        );

        self::assertNotSame(
            '',
            $request->method(),
        );

        self::assertTrue(
            str_starts_with(
                $request->uri()->value(),
                '/',
            ),
        );
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'A',
            1000000,
        );

        $request = Request::fromExternal(
            $payload,
            $payload,
            $payload,
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $request = Request::fromExternal(
            "\x00\x01\x02",
            "\x00\x01\x02",
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $payload = hex2bin('b131');

        $request = Request::fromExternal(
            $payload,
            $payload,
            $payload,
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    public function testItSupportsAllAllowedMethods(): void
    {
        $methods = [
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS',
        ];

        foreach ($methods as $method) {
            $request = new Request(
                new Uri('/orders'),
                $method,
            );

            self::assertSame(
                $method,
                $request->method(),
            );
        }
    }
}