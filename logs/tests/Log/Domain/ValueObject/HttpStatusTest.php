<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidHttpStatusException;
use App\Log\Domain\ValueObject\HttpStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject HttpStatus.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les bornes HTTP
 * - verrouiller les comportements de normalisation
 * - garantir la stabilité des helpers
 * - garantir la robustesse ingestion
 */
final class HttpStatusTest extends TestCase
{
    #[DataProvider('provideValidStatuses')]
    public function testItCreatesValidHttpStatus(
        int $value,
    ): void {
        $status = new HttpStatus($value);

        self::assertSame(
            $value,
            $status->value(),
        );
    }

    #[DataProvider('provideInvalidStatuses')]
    public function testItRejectsInvalidHttpStatus(
        int $value,
    ): void {
        $this->expectException(
            InvalidHttpStatusException::class,
        );

        new HttpStatus($value);
    }

    public function testItCreatesFromExternalInteger(): void
    {
        $status = HttpStatus::fromExternal(404);

        self::assertSame(
            404,
            $status->value(),
        );
    }

    public function testItCreatesFromExternalNumericString(): void
    {
        $status = HttpStatus::fromExternal('500');

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItFallsBackTo500ForInvalidString(): void
    {
        $status = HttpStatus::fromExternal(
            'invalid-status',
        );

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItFallsBackTo500ForNull(): void
    {
        $status = HttpStatus::fromExternal(null);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItFallsBackTo500ForTooSmallStatus(): void
    {
        $status = HttpStatus::fromExternal(99);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItFallsBackTo500ForTooLargeStatus(): void
    {
        $status = HttpStatus::fromExternal(600);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItDetectsInformationalStatus(): void
    {
        $status = new HttpStatus(102);

        self::assertTrue(
            $status->isInformational(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    public function testItDetectsSuccessStatus(): void
    {
        $status = new HttpStatus(200);

        self::assertTrue(
            $status->isSuccess(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    public function testItDetectsRedirectionStatus(): void
    {
        $status = new HttpStatus(302);

        self::assertTrue(
            $status->isRedirection(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    public function testItDetectsClientErrorStatus(): void
    {
        $status = new HttpStatus(404);

        self::assertTrue(
            $status->isClientError(),
        );

        self::assertTrue(
            $status->isError(),
        );
    }

    public function testItDetectsServerErrorStatus(): void
    {
        $status = new HttpStatus(500);

        self::assertTrue(
            $status->isServerError(),
        );

        self::assertTrue(
            $status->isError(),
        );
    }

    public function testItReturnsCorrectFamily(): void
    {
        self::assertSame(
            '2xx',
            (new HttpStatus(200))->family(),
        );

        self::assertSame(
            '4xx',
            (new HttpStatus(404))->family(),
        );

        self::assertSame(
            '5xx',
            (new HttpStatus(500))->family(),
        );
    }

    public function testItComparesTwoStatuses(): void
    {
        $left = new HttpStatus(404);
        $right = new HttpStatus(404);
        $other = new HttpStatus(500);

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $status = new HttpStatus(404);

        self::assertSame(
            '404',
            (string) $status,
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un HttpStatus valide
     */
    public function testItNeverThrowsFromExternal(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            '',
            'invalid',
            true,
            false,
            [],
            ['500'],
            new stdClass(),
            $resource,
            str_repeat('9', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            PHP_INT_MAX,
            PHP_INT_MIN,
            INF,
            -INF,
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertInstanceOf(
                HttpStatus::class,
                $status,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidHttpStatus(): void
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
            str_repeat('9', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertGreaterThanOrEqual(
                100,
                $status->value(),
            );

            self::assertLessThanOrEqual(
                599,
                $status->value(),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testItFallsBackTo500ForHostilePayloads(): void
    {
        $inputs = [
            null,
            'invalid',
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertSame(
                500,
                $status->value(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('9', 1000000);

        $status = HttpStatus::fromExternal($payload);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $status = HttpStatus::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            HttpStatus::class,
            $status,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $status = HttpStatus::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            HttpStatus::class,
            $status,
        );
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function provideValidStatuses(): iterable
    {
        yield 'minimum valid' => [100];

        yield 'success' => [200];

        yield 'redirection' => [302];

        yield 'client error' => [404];

        yield 'server error' => [500];

        yield 'maximum valid' => [599];
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function provideInvalidStatuses(): iterable
    {
        yield 'too small' => [99];

        yield 'negative' => [-1];

        yield 'zero' => [0];

        yield 'too large' => [600];

        yield 'very large' => [999];
    }
}