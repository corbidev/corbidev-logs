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
    /**
     * But : Vérifier qu'un IngestionWarning valide est correctement créé.
     *
     * Entrée : field='level', type=INVALID_LEVEL, original='LOL', fallback='error'
     * Résultat attendu : Chaque accesseur retourne la valeur fournie
     */
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

    /**
     * But : Vérifier que toArray() retourne le format de tableau attendu.
     *
     * Entrée : Warning avec field, type, original, fallback
     * Résultat attendu : tableau avec clés 'field', 'type', 'original', 'fallback'
     */
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

    /**
     * But : Vérifier que IngestionWarning implémente JsonSerializable et produit un JSON valide.
     *
     * Entrée : Warning valide
     * Résultat attendu : json_encode() produit un JSON valide
     */
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

    /**
     * But : Vérifier que original=null est accepté dans IngestionWarning.
     *
     * Entrée : original = null
     * Résultat attendu : original() retourne null, toArray() valide
     */
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

    /**
     * But : Vérifier que IngestionWarning accepte des booléens pour original et fallback.
     *
     * Entrée : original = true, fallback = false
     * Résultat attendu : toArray() retourne les valeurs correctes
     */
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

    /**
     * But : Vérifier que IngestionWarning accepte des valeurs numériques pour original et fallback.
     *
     * Entrée : original = 999999, fallback = 500
     * Résultat attendu : toArray() retourne les valeurs numériques correctes
     */
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

    /**
     * But : Vérifier que original est tronqué si sa représentation dépasse 500 caractères.
     *
     * Entrée : original = str_repeat('A', 5000)
     * Résultat attendu : original tronqué à ≤ 500 caractères
     */
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

    /**
     * But : Vérifier que fallback est tronqué si sa représentation dépasse 500 caractères.
     *
     * Entrée : fallback = str_repeat('B', 9000)
     * Résultat attendu : fallback tronqué à ≤ 500 caractères
     */
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

    /**
     * But : Vérifier que les tableaux sont normalisés en '[array]' dans le warning.
     *
     * Entrée : original = [1, 2, 3]
     * Résultat attendu : original dans toArray() = '[array]'
     */
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

    /**
     * But : Vérifier que les objets sont normalisés en '[object:ClassName]' dans le warning.
     *
     * Entrée : original = new stdClass()
     * Résultat attendu : original dans toArray() = '[object:stdClass]'
     */
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

    /**
     * But : Vérifier que les caractères Unicode et emoji sont acceptés sans crash.
     *
     * Entrée : original = 'Erreur 漢字 🚀'
     * Résultat attendu : JSON encodé sans erreur
     */
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

    /**
     * But : Vérifier que 2 000 instances avec payloads hostiles ne lèvent jamais d'exception.
     *
     * Entrée : 2 000 IngestionWarning avec values hostiles (SQL injection, XSS, binaire, etc.)
     * Résultat attendu : Toutes les instances créées sans exception
     */
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