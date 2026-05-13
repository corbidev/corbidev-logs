<?php

declare(strict_types=1);

namespace App\Tests\Log\Application\Normalizer;

use App\Log\Application\Normalizer\LogPayloadNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Tests fonctionnels du normalizer de payload.
 *
 * Ces tests vérifient les comportements attendus
 * sur des payloads valides ou partiellement valides.
 *
 * Objectifs :
 * - vérifier les valeurs conservées,
 * - vérifier les générations automatiques,
 * - garantir les invariants métier,
 * - valider les normalisations attendues,
 * - garantir la stabilité des données.
 */
final class LogPayloadNormalizerTest extends TestCase
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
     * But : Vérifier que normalize() conserve tous les champs d'un payload complet et valide.
     *
     * Entrée : Payload complet avec message, level, domain, env, requestId, externalId, request, context
     * Résultat attendu : Tous les champs conservés, query string supprimée, createdAt='2026-01-01T00:00:00+00:00'
     */
    public function testNormalizeCompletePayload(): void
    {
        $payload = [
            'message' => 'Paiement refusé',

            'level' => 'error',

            'domain' => 'billing',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'symfony-api',

            'requestId' => 'req_123456',

            'externalId'
                => '01963610-f9d2-7f5b-a13c-3b2f5f0e2f91',

            'request' => [
                'uri' => '/checkout?token=secret',
            ],

            'context' => [
                'userId' => 42,
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            'Paiement refusé',
            $normalized['message']
        );

        self::assertSame(
            'error',
            $normalized['level']
        );

        self::assertSame(
            'billing',
            $normalized['domain']
        );

        self::assertSame(
            'prod',
            $normalized['env']
        );

        self::assertSame(
            500,
            $normalized['httpStatus']
        );

        self::assertSame(
            'symfony-api',
            $normalized['client']
        );

        self::assertSame(
            'req_123456',
            $normalized['requestId']
        );

        self::assertSame(
            '01963610-f9d2-7f5b-a13c-3b2f5f0e2f91',
            $normalized['externalId']
        );

        self::assertSame(
            '/checkout',
            $normalized['request']['uri']
        );

        self::assertArrayHasKey(
            'fingerprint',
            $normalized
        );

        self::assertArrayHasKey(
            'createdAt',
            $normalized
        );

        self::assertSame(
            '2026-01-01T00:00:00+00:00',
            $normalized['createdAt']
        );
    }

    /**
     * But : Vérifier qu'un requestId est généré automatiquement s'il est absent.
     *
     * Entrée : Payload sans requestId
     * Résultat attendu : normalized['requestId'] non vide, commence par 'req_'
     */
    public function testGenerateRequestIdWhenMissing(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'api',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertArrayHasKey(
            'requestId',
            $normalized
        );

        self::assertNotEmpty(
            $normalized['requestId']
        );

        self::assertStringStartsWith(
            'req_',
            $normalized['requestId']
        );
    }

    /**
     * But : Vérifier qu'un externalId UUID v7 est généré automatiquement s'il est absent.
     *
     * Entrée : Payload sans externalId
     * Résultat attendu : normalized['externalId'] correspond à /^[0-9a-fA-F-]{36}$/
     */
    public function testGenerateExternalIdWhenMissing(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'api',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertArrayHasKey(
            'externalId',
            $normalized
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-fA-F-]{36}$/',
            $normalized['externalId']
        );
    }

    /**
     * But : Vérifier que createdAt est toujours fourni par l'horloge serveur injectée.
     *
     * Entrée : Payload quelconque, MockClock fixée au 2026-01-01T00:00:00+00:00
     * Résultat attendu : normalized['createdAt'] = '2026-01-01T00:00:00+00:00'
     */
    public function testGenerateCreatedAt(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'api',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertArrayHasKey(
            'createdAt',
            $normalized
        );

        self::assertSame(
            '2026-01-01T00:00:00+00:00',
            $normalized['createdAt']
        );
    }

    /**
     * But : Vérifier que le fingerprint généré fait exactement 16 caractères.
     *
     * Entrée : Payload avec message, level, domain, env, httpStatus, request['uri']
     * Résultat attendu : normalized['fingerprint'] présent, mb_strlen() = 16
     */
    public function testGenerateFingerprint(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'billing',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',

            'request' => [
                'uri' => '/checkout',
            ],
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertArrayHasKey(
            'fingerprint',
            $normalized
        );

        self::assertSame(
            16,
            mb_strlen($normalized['fingerprint'])
        );
    }

    /**
     * But : Vérifier que les clés sensibles du contexte sont masquées automatiquement.
     *
     * Entrée : context['password'] = 'secret-password', context['token'] = 'secret-token'
     * Résultat attendu : normalized['context']['password'] = '[FILTERED]', ['token'] = '[FILTERED]'
     */
    public function testFilterSensitiveData(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'billing',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',

            'context' => [
                'password' => 'secret-password',

                'token' => 'secret-token',
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
     * But : Vérifier que la query string est supprimée de l'URI normalisée.
     *
     * Entrée : request['uri'] = '/checkout?token=abc&user=42'
     * Résultat attendu : normalized['request']['uri'] = '/checkout'
     */
    public function testNormalizeUriWithoutQueryString(): void
    {
        $payload = [
            'message' => 'Erreur',

            'level' => 'error',

            'domain' => 'billing',

            'env' => 'prod',

            'httpStatus' => 500,

            'client' => 'backend',

            'request' => [
                'uri'
                    => '/checkout?token=abc&user=42',
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
     * But : Vérifier que /users/1 et /users/999 produisent le même fingerprint.
     *
     * Entrée : payload1 avec '/users/1', payload2 avec '/users/999'
     * Résultat attendu : normalized1['fingerprint'] === normalized2['fingerprint']
     */
    public function testFingerprintNormalizesNumericUris(): void
    {
        $payload1 = [
            'level' => 'error',
            'domain' => 'api',
            'env' => 'prod',
            'httpStatus' => 500,

            'request' => [
                'uri' => '/users/1',
            ],
        ];

        $payload2 = [
            'level' => 'error',
            'domain' => 'api',
            'env' => 'prod',
            'httpStatus' => 500,

            'request' => [
                'uri' => '/users/999',
            ],
        ];

        $normalized1 = $this->normalizer->normalize(
            $payload1
        );

        $normalized2 = $this->normalizer->normalize(
            $payload2
        );

        self::assertSame(
            $normalized1['fingerprint'],
            $normalized2['fingerprint']
        );
    }

    /**
     * But : Vérifier qu'un UUID valide dans externalId est conservé sans modification.
     *
     * Entrée : externalId = '01963610-f9d2-7f5b-a13c-3b2f5f0e2f91'
     * Résultat attendu : normalized['externalId'] = l'UUID d'origine
     */
    public function testKeepValidExternalId(): void
    {
        $uuid = '01963610-f9d2-7f5b-a13c-3b2f5f0e2f91';

        $payload = [
            'externalId' => $uuid,
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            $uuid,
            $normalized['externalId']
        );
    }

    /**
     * But : Vérifier qu'un requestId valide est conservé sans modification.
     *
     * Entrée : requestId = 'req_checkout_123'
     * Résultat attendu : normalized['requestId'] = 'req_checkout_123'
     */
    public function testKeepValidRequestId(): void
    {
        $payload = [
            'requestId' => 'req_checkout_123',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertSame(
            'req_checkout_123',
            $normalized['requestId']
        );
    }

    /**
     * But : Vérifier qu'une date client valide est normalisée correctement.
     *
     * Entrée : clientDate = '2026-01-01 10:00:00'
     * Résultat attendu : normalized['clientDate'] non null
     */
    public function testNormalizeValidClientDate(): void
    {
        $payload = [
            'clientDate'
                => '2026-01-01 10:00:00',
        ];

        $normalized = $this->normalizer->normalize(
            $payload
        );

        self::assertNotNull(
            $normalized['clientDate']
        );
    }

    /**
     * But : Vérifier que les valeurs par défaut sont bien appliquées pour un payload vide.
     *
     * Entrée : Payload vide []
     * Résultat attendu : message='Unknown error', level='error', domain='unknown', env='prod', httpStatus=500, client='unknown-client'
     */
    public function testFallbackValuesWhenFieldsMissing(): void
    {
        $normalized = $this->normalizer->normalize(
            []
        );

        self::assertSame(
            'Unknown error',
            $normalized['message']
        );

        self::assertSame(
            'error',
            $normalized['level']
        );

        self::assertSame(
            'unknown',
            $normalized['domain']
        );

        self::assertSame(
            'prod',
            $normalized['env']
        );

        self::assertSame(
            500,
            $normalized['httpStatus']
        );

        self::assertSame(
            'unknown-client',
            $normalized['client']
        );
    }
}