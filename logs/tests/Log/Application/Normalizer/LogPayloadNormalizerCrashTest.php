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
     * But : Vérifier que le niveau de log invalide est remplacé par le fallback.
     *
     * Entrée : level = 'LOL'
     * Résultat attendu : level normalisé = 'error'
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
     * But : Vérifier que le status HTTP invalide est remplacé par le fallback 500.
     *
     * Entrée : httpStatus = 'abc'
     * Résultat attendu : httpStatus = 500
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
     * But : Vérifier qu'un externalId invalide génère automatiquement un nouvel UUID valide.
     *
     * Entrée : externalId = 'invalid-id'
     * Résultat attendu : externalId remplacé par un UUID valide (format /^[0-9a-fA-F-]{36}$/)
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
     * But : Vérifier qu'un requestId invalide est automatiquement régénéré.
     *
     * Entrée : requestId = '@@@@@@@'
     * Résultat attendu : Nouveau requestId commençant par 'req_', différent de l'original
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
    /**
     * But : Vérifier qu'un message de 10 000 caractères est tronqué.
     *
     * Entrée : message = str_repeat('A', 10000)
     * Résultat attendu : message tronqué à ≤ 1 000 caractères
     */
    public function testHugeStringIsTruncated(): void

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
    /**
     * But : Vérifier qu'un contexte de 1 000 éléments est limité à 50.
     *
     * Entrée : context = range(1, 1000)
     * Résultat attendu : context limité à 50 éléments
     */
    public function testHugeArrayIsLimited(): void
            $normalized['context']
        );
    }

    /**
     * But : Vérifier qu'un context de type string retourne toujours un tableau.
     *
     * Entrée : context = 'invalid' (string)
     * Résultat attendu : context normalisé = []
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
     * But : Vérifier qu'un extra de type entier retourne toujours un tableau.
     *
     * Entrée : extra = 123 (entier)
     * Résultat attendu : extra normalisé = []
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
     * But : Vérifier qu'un tags de type booléen retourne toujours un tableau.
     *
     * Entrée : tags = false
     * Résultat attendu : tags normalisé = []
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
     * But : Vérifier qu'un payload vide ne provoque aucune erreur.
     *
     * Entrée : payload = []
     * Résultat attendu : Un tableau est retourné, aucune exception levée
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
    /**
     * But : Vérifier qu'une structure récursive ne provoque aucun crash.
     *
     * Entrée : Tableau auto-référencé ($payload['recursive'] = &$payload)
     * Résultat attendu : Un tableau est retourné, aucune exception ni stack overflow
     */
    public function testRecursivePayloadDoesNotCrash(): void

    /**
     * But : Vérifier qu'une URI invalide est remplacée par le fallback '/'.
     *
     * Entrée : request.uri = 12345 (entier)
     * Résultat attendu : request.uri = '/'
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
    /**
     * But : Vérifier que la query string est supprimée de l'URI normalisée.
     *
     * Entrée : request.uri = '/checkout?token=secret'
     * Résultat attendu : request.uri = '/checkout'
     */
    public function testUriQueryStringIsRemoved(): void

        self::assertSame(
            '/checkout',
            $normalized['request']['uri']
        );
    }

    /**
     * But : Vérifier qu'une structure d'exception invalide ne provoque aucun crash.
     *
     * Entrée : exception = 'boom' (string)
     * Résultat attendu : exception normalisée = tableau
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
     * Entrée : exception.trace avec 1 000 entrées
     * Résultat attendu : trace limitée à 50 éléments
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
     * But : Vérifier qu'une date client invalide est ignorée sans crash.
     *
     * Entrée : clientDate = 'not-a-date'
     * Résultat attendu : clientDate = null
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
     * But : Vérifier qu'un objet sans __toString() dans le contexte est sérialisé sans crash.
     *
     * Entrée : context = ['object' => new stdClass()]
     * Résultat attendu : context.object contient '[OBJECT...' (string)
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
     * But : Vérifier que de l'UTF-8 invalide dans le message ne provoque aucun crash.
     *
     * Entrée : message = "\xB1\x31" (UTF-8 invalide)
     * Résultat attendu : message normalisé est une string
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
     * But : Vérifier que les données sensibles dans le contexte sont filtrées.
     *
     * Entrée : context.password = 'secret', context.token = '123456'
     * Résultat attendu : context.password = '[FILTERED]', context.token = '[FILTERED]'
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
     * But : Vérifier que createdAt utilise l'horloge injectée dans le normaliseur.
     *
     * Entrée : Normaliseur configuré avec une horloge fixée à '2026-01-01T00:00:00+00:00'
     * Résultat attendu : createdAt = '2026-01-01T00:00:00+00:00'
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