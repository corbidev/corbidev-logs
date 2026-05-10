<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\ValueObject\Tags;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Tags.
 *
 * Objectifs :
 * - garantir les normalisations
 * - garantir les bornes ingestion
 * - garantir les données sûres
 * - garantir la robustesse face aux payloads hostiles
 */
final class TagsTest extends TestCase
{
    public function testItCreatesValidTags(): void
    {
        $tags = new Tags([
            'feature' => 'checkout',
            'region' => 'eu-west',
        ]);

        self::assertSame(
            [
                'feature' => 'checkout',
                'region' => 'eu-west',
            ],
            $tags->values(),
        );
    }

    public function testItNormalizesKeys(): void
    {
        $tags = new Tags([
            ' FEATURE ' => 'checkout',
            'Region' => 'eu',
        ]);

        self::assertSame(
            [
                'feature' => 'checkout',
                'region' => 'eu',
            ],
            $tags->values(),
        );
    }

    public function testItTrimsValues(): void
    {
        $tags = new Tags([
            'feature' => '  checkout  ',
        ]);

        self::assertSame(
            'checkout',
            $tags->get('feature'),
        );
    }

    public function testItRejectsInvalidKeys(): void
    {
        $tags = new Tags([
            '<script>' => 'x',
            'valid-key' => 'ok',
        ]);

        self::assertSame(
            [
                'valid-key' => 'ok',
            ],
            $tags->values(),
        );
    }

    public function testItRejectsInvalidValues(): void
    {
        $tags = new Tags([
            'feature' => ['invalid'],
            'region' => 'eu',
        ]);

        self::assertSame(
            [
                'region' => 'eu',
            ],
            $tags->values(),
        );
    }

    public function testItIgnoresEmptyKeys(): void
    {
        $tags = new Tags([
            '' => 'value',
            '   ' => 'value',
            'feature' => 'checkout',
        ]);

        self::assertSame(
            [
                'feature' => 'checkout',
            ],
            $tags->values(),
        );
    }

    public function testItIgnoresEmptyValues(): void
    {
        $tags = new Tags([
            'feature' => '',
            'region' => '   ',
            'env' => 'prod',
        ]);

        self::assertSame(
            [
                'env' => 'prod',
            ],
            $tags->values(),
        );
    }

    public function testItLimitsKeyLength(): void
    {
        $key = str_repeat('a', 100);

        $tags = new Tags([
            $key => 'value',
        ]);

        $normalized = array_keys(
            $tags->values(),
        )[0];

        self::assertSame(
            50,
            mb_strlen($normalized),
        );
    }

    public function testItLimitsValueLength(): void
    {
        $value = str_repeat('a', 500);

        $tags = new Tags([
            'feature' => $value,
        ]);

        self::assertSame(
            100,
            mb_strlen(
                $tags->get('feature') ?? '',
            ),
        );
    }

    public function testItLimitsTagCount(): void
    {
        $payload = [];

        for ($i = 0; $i < 100; $i++) {
            $payload['tag-' . $i] = 'value';
        }

        $tags = new Tags($payload);

        self::assertCount(
            50,
            $tags->values(),
        );
    }

    public function testItSortsKeys(): void
    {
        $tags = new Tags([
            'zeta' => '1',
            'alpha' => '2',
            'beta' => '3',
        ]);

        self::assertSame(
            [
                'alpha' => '2',
                'beta' => '3',
                'zeta' => '1',
            ],
            $tags->values(),
        );
    }

    public function testItDetectsExistingTag(): void
    {
        $tags = new Tags([
            'feature' => 'checkout',
        ]);

        self::assertTrue(
            $tags->has('feature'),
        );
    }

    public function testItDetectsMissingTag(): void
    {
        $tags = new Tags([
            'feature' => 'checkout',
        ]);

        self::assertFalse(
            $tags->has('missing'),
        );
    }

    public function testItReturnsDefaultValue(): void
    {
        $tags = new Tags([]);

        self::assertSame(
            'fallback',
            $tags->get(
                'missing',
                'fallback',
            ),
        );
    }

    public function testItDetectsEmptyTags(): void
    {
        $tags = new Tags([]);

        self::assertTrue(
            $tags->isEmpty(),
        );
    }

    public function testItCountsTags(): void
    {
        $tags = new Tags([
            'feature' => 'checkout',
            'region' => 'eu',
        ]);

        self::assertSame(
            2,
            $tags->count(),
        );
    }

    public function testItComparesTags(): void
    {
        $left = new Tags([
            'feature' => 'checkout',
        ]);

        $right = new Tags([
            'feature' => 'checkout',
        ]);

        $other = new Tags([
            'feature' => 'billing',
        ]);

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    public function testItReturnsStableStringRepresentation(): void
    {
        $tags = new Tags([
            'feature' => 'checkout',
        ]);

        self::assertSame(
            '{"feature":"checkout"}',
            (string) $tags,
        );
    }

    public function testItCreatesFromExternalArray(): void
    {
        $tags = Tags::fromExternal([
            'feature' => 'checkout',
        ]);

        self::assertSame(
            'checkout',
            $tags->get('feature'),
        );
    }

    public function testItFallsBackForInvalidExternalPayload(): void
    {
        $tags = Tags::fromExternal(
            'invalid',
        );

        self::assertSame(
            [],
            $tags->values(),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Tags valide
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
            ['feature' => 'checkout'],
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
            $tags = Tags::fromExternal($input);

            self::assertInstanceOf(
                Tags::class,
                $tags,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testFromExternalAlwaysReturnsValidTags(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            true,
            false,
            123,
            [],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $tags = Tags::fromExternal($input);

            self::assertLessThanOrEqual(
                50,
                $tags->count(),
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
            true,
            false,
            123,
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
            "'; DROP TABLE logs; --",
        ];

        foreach ($inputs as $input) {
            $tags = Tags::fromExternal($input);

            self::assertSame(
                [],
                $tags->values(),
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = [];

        for ($i = 0; $i < 100000; $i++) {
            $payload['tag-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $tags = Tags::fromExternal($payload);

        self::assertLessThanOrEqual(
            50,
            $tags->count(),
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $tags = Tags::fromExternal([
            'feature' => "\x00\x01\x02",
        ]);

        self::assertInstanceOf(
            Tags::class,
            $tags,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $tags = Tags::fromExternal([
            'feature' => hex2bin('b131'),
        ]);

        self::assertInstanceOf(
            Tags::class,
            $tags,
        );
    }
}