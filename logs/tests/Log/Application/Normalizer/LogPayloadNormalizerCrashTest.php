<?php

declare(strict_types=1);

namespace App\Tests\Log\Application\Normalizer;

use App\Log\Application\Normalizer\LogPayloadNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Tests de robustesse et anti-crash du normalizer.
 *
 * Ces tests garantissent que le système :
 * - ne plante jamais,
 * - résiste aux payloads hostiles,
 * - corrige les données invalides,
 * - reste prédictible,
 * - conserve des bornes mémoire stables.
 *
 * Ces tests sont critiques pour la stabilité
 * du pipeline d'ingestion.
 */
final class LogPayloadNormalizerCrashTest extends TestCase
{
    /**
     * Service de normalisation testé.
     */
    private LogPayloadNormalizer $normalizer;

    /**
     * Initialise le normalizer avant chaque test.
     *
     * Utilise une horloge figée afin de :
     * - rendre les tests déterministes,
     * - stabiliser les snapshots,
     * - éviter les tests flaky.
     */
    protected function setUp(): void
    {
        $clock = new MockClock(
            '2026-01-01T00:00:00+00:00'
        );

        $this->normalizer = new LogPayloadNormalizer(
            $clock
        );
    }

    /**
     * Vérifie qu'un niveau de log invalide
     * est remplacé par la valeur fallback.
     */
    public function testInvalidLevelFallback(): void
    {
        $payload = [
            'level' => 'LOL',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            'error',
            $normalized['level']
        );
    }

    /**
     * Vérifie qu'un status HTTP invalide
     * est remplacé par la valeur fallback.
     */
    public function testInvalidHttpStatusFallback(): void
    {
        $payload = [
            'httpStatus' => 'abc',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            500,
            $normalized['httpStatus']
        );
    }

    /**
     * Vérifie qu'un externalId invalide
     * génère automatiquement un UUID v7 valide.
     */
    public function testInvalidExternalIdGeneratesNewUuid(): void
    {
        $payload = [
            'externalId' => 'invalid-id',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-fA-F-]{36}$/',
            $normalized['externalId']
        );
    }

    /**
     * Vérifie qu'un requestId invalide
     * est automatiquement régénéré.
     */
    public function testInvalidRequestIdGeneratesNewOne(): void
    {
        $payload = [
            'requestId' => '@@@@@@@',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertNotSame(
            '@@@@@@@',
            $normalized['requestId']
        );

        self::assertStringStartsWith(
            'req_',
            $normalized['requestId']
        );
    }

    /**
     * Vérifie qu'une chaîne trop longue
     * est automatiquement tronquée.
     *
     * Cela protège :
     * - la mémoire,
     * - les performances,
     * - la taille des payloads,
     * - les écritures disque.
     */
    public function testHugeStringIsTruncated(): void
    {
        $payload = [
            'message' => str_repeat(
                'A',
                10000
            ),
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertLessThanOrEqual(
            1000,
            mb_strlen($normalized['message'])
        );
    }

    /**
     * Vérifie qu'un tableau trop volumineux
     * est limité automatiquement.
     *
     * Cela protège :
     * - mémoire,
     * - CPU,
     * - taille des logs,
     * - stabilité du pipeline.
     */
    public function testHugeArrayIsLimited(): void
    {
        $payload = [
            'context' => range(1, 1000),
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertCount(
            50,
            $normalized['context']
        );
    }

    /**
     * Vérifie qu'un context invalide
     * retourne toujours un tableau.
     */
    public function testInvalidContextTypeReturnsArray(): void
    {
        $payload = [
            'context' => 'invalid',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsArray(
            $normalized['context']
        );
    }

    /**
     * Vérifie qu'un extra invalide
     * retourne toujours un tableau.
     */
    public function testInvalidExtraTypeReturnsArray(): void
    {
        $payload = [
            'extra' => 123,
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsArray(
            $normalized['extra']
        );
    }

    /**
     * Vérifie qu'un tags invalide
     * retourne toujours un tableau.
     */
    public function testInvalidTagsTypeReturnsArray(): void
    {
        $payload = [
            'tags' => false,
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsArray(
            $normalized['tags']
        );
    }

    /**
     * Vérifie qu'un payload vide
     * ne provoque jamais d'erreur fatale.
     */
    public function testNullPayloadDoesNotCrash(): void
    {
        $normalized = $this->normalizer->normalize(
            []
        );

        self::assertIsArray($normalized);
    }

    /**
     * Vérifie qu'une structure récursive
     * ne provoque jamais de crash.
     *
     * Ce test protège contre :
     * - récursions infinies,
     * - dépassements mémoire,
     * - stack overflows,
     * - payloads hostiles.
     */
    public function testRecursivePayloadDoesNotCrash(): void
    {
        $payload = [];

        $payload['recursive'] = &$payload;

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsArray($normalized);
    }

    /**
     * Vérifie qu'une URI invalide
     * retourne une valeur fallback sûre.
     */
    public function testInvalidUriFallback(): void
    {
        $payload = [
            'request' => [
                'uri' => 12345,
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            '/',
            $normalized['request']['uri']
        );
    }

    /**
     * Vérifie qu'une query string
     * est supprimée de l'URI.
     *
     * Cela protège :
     * - les fingerprints,
     * - les données sensibles,
     * - les tokens.
     */
    public function testUriQueryStringIsRemoved(): void
    {
        $payload = [
            'request' => [
                'uri' => '/checkout?token=secret',
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            '/checkout',
            $normalized['request']['uri']
        );
    }

    /**
     * Vérifie qu'une exception invalide
     * ne provoque jamais de crash.
     */
    public function testInvalidExceptionStructureDoesNotCrash(): void
    {
        $payload = [
            'exception' => 'boom',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsArray(
            $normalized['exception']
        );
    }

    /**
     * Vérifie qu'une stacktrace énorme
     * est automatiquement limitée.
     */
    public function testHugeTraceIsLimited(): void
    {
        $trace = [];

        for ($i = 0; $i < 1000; $i++) {
            $trace[] = [
                'file' => '/file.php',
                'line' => $i,
            ];
        }

        $payload = [
            'exception' => [
                'trace' => $trace,
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertCount(
            50,
            $normalized['exception']['trace']
        );
    }

    /**
     * Vérifie qu'une date client invalide
     * est ignorée proprement.
     */
    public function testInvalidClientDateDoesNotCrash(): void
    {
        $payload = [
            'clientDate' => 'not-a-date',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertNull(
            $normalized['clientDate']
        );
    }

    /**
     * Vérifie qu'un objet sans __toString()
     * est sécurisé proprement.
     */
    public function testObjectWithoutToStringDoesNotCrash(): void
    {
        $payload = [
            'context' => [
                'object' => new \stdClass(),
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertStringContainsString(
            '[OBJECT',
            $normalized['context']['object']
        );
    }

    /**
     * Vérifie qu'un UTF8 invalide
     * ne provoque jamais de crash.
     */
    public function testInvalidUtf8DoesNotCrash(): void
    {
        $payload = [
            'message' => "\xB1\x31",
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertIsString(
            $normalized['message']
        );
    }

    /**
     * Vérifie que les données sensibles
     * sont toujours filtrées.
     */
    public function testSensitiveDataIsFiltered(): void
    {
        $payload = [
            'context' => [
                'password' => 'secret',
                'token' => '123456',
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            '[FILTERED]',
            $normalized['context']['password']
        );

        self::assertSame(
            '[FILTERED]',
            $normalized['context']['token']
        );
    }

    /**
     * Vérifie que createdAt
     * utilise bien l'horloge injectée.
     */
    public function testCreatedAtUsesInjectedClock(): void
    {
        $normalized = $this->normalizer->normalize(
            []
        );

        self::assertSame(
            '2026-01-01T00:00:00+00:00',
            $normalized['createdAt']
        );
    }
}