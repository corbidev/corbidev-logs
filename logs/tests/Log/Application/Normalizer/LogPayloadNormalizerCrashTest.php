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
     * But : Vérifier qu'un niveau de log invalide est remplacé par la valeur fallback.
     *
     * Entrée : payload['level'] = 'LOL'
     * Résultat attendu : normalized['level'] = 'error'
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
     * But : Vérifier qu'un status HTTP invalide est remplacé par 500.
     *
     * Entrée : payload['httpStatus'] = 'abc'
     * Résultat attendu : normalized['httpStatus'] = 500
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
     * But : Vérifier qu'un externalId invalide est remplacé par un UUID v7 généré.
     *
     * Entrée : payload['externalId'] = 'invalid-id'
     * Résultat attendu : normalized['externalId'] correspond à /^[0-9a-fA-F-]{36}$/
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
     * But : Vérifier qu'un requestId invalide est régénéré avec le préfixe 'req_'.
     *
     * Entrée : payload['requestId'] = '@@@@@@@'
     * Résultat attendu : normalized['requestId'] != '@@@@@@@', commence par 'req_'
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
     * But : Vérifier qu'une chaîne très longue est tronquée.
     *
     * Entrée : payload['message'] = str_repeat('A', 10000)
     * Résultat attendu : mb_strlen(normalized['message']) <= 1000
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
     * But : Vérifier qu'un tableau trop volumineux est limité à 50 entrées.
     *
     * Entrée : payload['context'] = range(1, 1000)
     * Résultat attendu : count(normalized['context']) = 50
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
     * But : Vérifier qu'une valeur non-tableau pour context est convertie en tableau vide.
     *
     * Entrée : payload['context'] = 'invalid'
     * Résultat attendu : is_array(normalized['context']) = true
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
     * But : Vérifier qu'une valeur non-tableau pour extra est convertie en tableau vide.
     *
     * Entrée : payload['extra'] = 123
     * Résultat attendu : is_array(normalized['extra']) = true
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
     * But : Vérifier qu'une valeur non-tableau pour tags est convertie en tableau vide.
     *
     * Entrée : payload['tags'] = false
     * Résultat attendu : is_array(normalized['tags']) = true
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
     * But : Vérifier que normalize([]) ne lève aucune exception et retourne un tableau.
     *
     * Entrée : Tableau vide []
     * Résultat attendu : is_array(normalized) = true
     */
    public function testNullPayloadDoesNotCrash(): void
    {
        $normalized = $this->normalizer->normalize(
            []
        );

        self::assertIsArray($normalized);
    }

    /**
     * But : Vérifier qu'une structure récursive ne provoque aucun crash.
     *
     * Entrée : payload avec référence circulaire $payload['recursive'] = &$payload
     * Résultat attendu : is_array(normalized) = true, aucune exception
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
     * But : Vérifier qu'une URI invalide est remplacée par '/'.
     *
     * Entrée : payload['request']['uri'] = 12345
     * Résultat attendu : normalized['request']['uri'] = '/'
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
     * But : Vérifier que la query string est supprimée de l'URI normalisée.
     *
     * Entrée : payload['request']['uri'] = '/checkout?token=secret'
     * Résultat attendu : normalized['request']['uri'] = '/checkout'
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
     * But : Vérifier qu'une structure d'exception invalide ne provoque aucun crash.
     *
     * Entrée : payload['exception'] = 'boom'
     * Résultat attendu : is_array(normalized['exception']) = true
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
     * But : Vérifier qu'une stacktrace de 1 000 entrées est limitée à 50.
     *
     * Entrée : payload['exception']['trace'] = [1000 entrées]
     * Résultat attendu : count(normalized['exception']['trace']) = 50
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
     * But : Vérifier qu'une date client invalide est ignorée (null retorné).
     *
     * Entrée : payload['clientDate'] = 'not-a-date'
     * Résultat attendu : normalized['clientDate'] = null
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
     * But : Vérifier qu'un objet sans __toString() dans context est converti en chaîne '[OBJECT...]'.
     *
     * Entrée : payload['context']['object'] = new \stdClass()
     * Résultat attendu : normalized['context']['object'] contient '[OBJECT'
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
     * But : Vérifier que du contenu UTF-8 invalide dans le payload ne provoque aucun crash.
     *
     * Entrée : payload['message'] = "\xB1\x31"
     * Résultat attendu : is_string(normalized['message']) = true
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
     * But : Vérifier que les clés sensibles (password, token) sont toujours masquées.
     *
     * Entrée : payload['context']['password'] = 'secret', ['token'] = '123456'
     * Résultat attendu : normalized['context']['password'] = '[FILTERED]', normalized['context']['token'] = '[FILTERED]'
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
     * But : Vérifier que createdAt utilise l'horloge injectée (MockClock).
     *
     * Entrée : MockClock fixée au 2026-01-01T00:00:00+00:00
     * Résultat attendu : normalized['createdAt'] = '2026-01-01T00:00:00+00:00'
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