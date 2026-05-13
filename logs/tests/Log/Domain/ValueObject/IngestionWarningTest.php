<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\ValueObject\IngestionWarning;
use App\Log\Enum\IngestionWarningType;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires et crash tests
 * de IngestionWarning.
 *
 * OBJECTIFS :
 * -----------
 * - garantir immutabilité
 * - garantir sérialisation stable
 * - garantir robustesse
 * - garantir absence de crash
 * - garantir données bornées
 *
 * IMPORTANT :
 * ------------
 * IngestionWarning doit :
 * - toujours être sérialisable
 * - ne jamais throw
 * - rester stable avec données hostiles
 * - rester compatible JSON
 */
final class IngestionWarningTest extends TestCase
{
    public function testItCreatesValidWarning(): void
    {
        $warning = new IngestionWarning(
            field: 'ip',
            type: IngestionWarningType::INVALID_IP,
            original: '999.999.999.999',
            fallback: '127.0.0.1',
        );

        self::assertSame(
            'ip',
            $warning->field(),
        );

        self::assertSame(
            IngestionWarningType::INVALID_IP,
            $warning->type(),
        );

        self::assertSame(
            '999.999.999.999',
            $warning->original(),
        );

        self::assertSame(
            '127.0.0.1',
            $warning->fallback(),
        );
    }

    public function testItSerializesToArray(): void
    {
        $warning = new IngestionWarning(
            field: 'message',
            type: IngestionWarningType::MESSAGE_TRUNCATED,
            original: 'huge message',
            fallback: 'truncated',
        );

        self::assertSame(
            [
                'field' => 'message',
                'type' => 'message_truncated',
                'original' => 'huge message',
                'fallback' => 'truncated',
            ],
            $warning->toArray(),
        );
    }

    public function testItImplementsJsonSerializable(): void
    {
        $warning = new IngestionWarning(
            field: 'method',
            type: IngestionWarningType::INVALID_METHOD,
            original: 'INVALID',
            fallback: 'GET',
        );

        $json = json_encode(
            $warning,
            JSON_THROW_ON_ERROR,
        );

        self::assertJson(
            $json,
        );

        self::assertStringContainsString(
            'invalid_method',
            $json,
        );
    }

    public function testItSupportsNullValues(): void
    {
        $warning = new IngestionWarning(
            field: 'requestId',
            type: IngestionWarningType::INVALID_REQUEST_ID,
            original: null,
            fallback: 'generated-id',
        );

        self::assertNull(
            $warning->original(),
        );

        self::assertSame(
            'generated-id',
            $warning->fallback(),
        );
    }

    public function testItSupportsBooleanValues(): void
    {
        $warning = new IngestionWarning(
            field: 'debug',
            type: IngestionWarningType::INVALID_LEVEL,
            original: true,
            fallback: false,
        );

        self::assertTrue(
            $warning->original(),
        );

        self::assertFalse(
            $warning->fallback(),
        );
    }

    public function testItSupportsNumericValues(): void
    {
        $warning = new IngestionWarning(
            field: 'httpStatus',
            type: IngestionWarningType::INVALID_HTTP_STATUS,
            original: 999999,
            fallback: 500,
        );

        self::assertSame(
            999999,
            $warning->original(),
        );

        self::assertSame(
            500,
            $warning->fallback(),
        );
    }

    public function testItTruncatesHugeOriginalString(): void
    {
        $warning = new IngestionWarning(
            field: 'message',
            type: IngestionWarningType::MESSAGE_TRUNCATED,
            original: str_repeat(
                'A',
                5000,
            ),
            fallback: 'truncated',
        );

        $data = $warning->toArray();

        self::assertSame(
            500,
            mb_strlen(
                $data['original'],
            ),
        );
    }

    public function testItTruncatesHugeFallbackString(): void
    {
        $warning = new IngestionWarning(
            field: 'userAgent',
            type: IngestionWarningType::USER_AGENT_TRUNCATED,
            original: 'huge',
            fallback: str_repeat(
                'B',
                9000,
            ),
        );

        $data = $warning->toArray();

        self::assertSame(
            500,
            mb_strlen(
                $data['fallback'],
            ),
        );
    }

    public function testItNormalizesArrays(): void
    {
        $warning = new IngestionWarning(
            field: 'context',
            type: IngestionWarningType::CONTEXT_TRUNCATED,
            original: [
                'huge' => true,
            ],
            fallback: [],
        );

        self::assertSame(
            '[array]',
            $warning
                ->toArray()['original'],
        );

        self::assertSame(
            '[array]',
            $warning
                ->toArray()['fallback'],
        );
    }

    public function testItNormalizesObjects(): void
    {
        $object = new \stdClass();

        $warning = new IngestionWarning(
            field: 'payload',
            type: IngestionWarningType::PAYLOAD_DEPTH_TRUNCATED,
            original: $object,
            fallback: null,
        );

        self::assertSame(
            '[object:stdClass]',
            $warning
                ->toArray()['original'],
        );
    }

    public function testItSupportsUnicode(): void
    {
        $warning = new IngestionWarning(
            field: 'message',
            type: IngestionWarningType::INVALID_UTF8_REMOVED,
            original: 'Erreur 漢字 🚀',
            fallback: 'Erreur',
        );

        $json = json_encode(
            $warning,
            JSON_THROW_ON_ERROR,
        );

        self::assertJson(
            $json,
        );
    }

    public function testItNeverThrowsWithHostilePayloads(): void
    {
        for ($i = 0; $i < 2000; ++$i) {
            $warning = new IngestionWarning(
                field: str_repeat(
                    'field',
                    200,
                ),

                type: IngestionWarningType::MESSAGE_TRUNCATED,

                original: str_repeat(
                    'payload',
                    1000,
                ),

                fallback: random_bytes(
                    50,
                ),
            );

            self::assertInstanceOf(
                IngestionWarning::class,
                $warning,
            );

            self::assertIsArray(
                $warning->toArray(),
            );
        }
    }

    public function testItSurvivesMassiveSerializationLoop(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            $warning = new IngestionWarning(
                field: 'field_' . $i,
                type: IngestionWarningType::INVALID_IP,
                original: 'invalid-ip-' . $i,
                fallback: '127.0.0.1',
            );

            $json = json_encode(
                $warning,
                JSON_THROW_ON_ERROR,
            );

            self::assertJson(
                $json,
            );
        }
    }

    public function testItProducesStableJsonStructure(): void
    {
        $warning = new IngestionWarning(
            field: 'uri',
            type: IngestionWarningType::INVALID_URI,
            original: '/../../../../../etc/passwd',
            fallback: '/',
        );

        $data = $warning->jsonSerialize();

        self::assertArrayHasKey(
            'field',
            $data,
        );

        self::assertArrayHasKey(
            'type',
            $data,
        );

        self::assertArrayHasKey(
            'original',
            $data,
        );

        self::assertArrayHasKey(
            'fallback',
            $data,
        );
    }

    public function testItHandlesUnsupportedResourceGracefully(): void
    {
        $resource = fopen(
            'php://memory',
            'rb',
        );

        $warning = new IngestionWarning(
            field: 'resource',
            type: IngestionWarningType::INVALID_UTF8_REMOVED,
            original: $resource,
            fallback: null,
        );

        self::assertSame(
            '[unsupported]',
            $warning
                ->toArray()['original'],
        );

        fclose(
            $resource,
        );
    }
}