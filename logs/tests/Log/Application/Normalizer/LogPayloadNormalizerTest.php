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
     * Vérifie qu'un payload entièrement valide
     * conserve correctement toutes ses valeurs.
     *
     * Vérifie également :
     * - la génération du fingerprint,
     * - la génération du createdAt,
     * - la suppression des query strings,
     * - la stabilité des données.
     */
    public function testNormalizeCompletePayload(): void
    /**
     * But : Vérifier que toutes les valeurs d'un payload valide sont conservées après normalisation.
     *
     * Entrée : Payload complet avec message, level, domain, env, httpStatus, client, requestId, externalId, request, context
     * Résultat attendu : Toutes les valeurs correctement normalisées, uri sans query string, createdAt = horloge serveur
     */
    public function testNormalizeCompletePayload(): void

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
     * Vérifie qu'un requestId est automatiquement généré
     * lorsqu'il est absent du payload.
    /**
     * But : Vérifier qu'un requestId est généré automatiquement lorsqu'absent du payload.
     *
     * Entrée : Payload sans champ requestId
     * Résultat attendu : requestId non vide commençant par 'req_'
     */
    public function testGenerateRequestIdWhenMissing(): void

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
     * Vérifie qu'un externalId est automatiquement généré
     * lorsqu'il est absent.
    /**
     * But : Vérifier qu'un externalId est généré automatiquement lorsqu'absent du payload.
     *
     * Entrée : Payload sans champ externalId
     * Résultat attendu : externalId valide au format UUID (/^[0-9a-fA-F-]{36}$/)
     */
    public function testGenerateExternalIdWhenMissing(): void

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
     * Vérifie que la date serveur createdAt
     * est toujours générée.
     *
     * Cette valeur :
     * - ne dépend jamais du client,
     * - dépend uniquement de l'horloge serveur.
     */
    public function testGenerateCreatedAt(): void
    /**
     * But : Vérifier que createdAt est toujours la date serveur (horloge injectée).
     *
     * Entrée : Normaliseur avec horloge fixée à '2026-01-01T00:00:00+00:00'
     * Résultat attendu : createdAt = '2026-01-01T00:00:00+00:00'
     */
    public function testGenerateCreatedAt(): void

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
     * Vérifie que le fingerprint
     * est correctement généré.
     *
     * Le fingerprint permet :
     * - le regroupement d'erreurs,
     * - les statistiques,
     * - la déduplication.
     */
    public function testGenerateFingerprint(): void
    /**
     * But : Vérifier que le fingerprint est généré avec une longueur de 16 caractères.
     *
     * Entrée : Payload valide avec level, domain, env, httpStatus, request.uri
     * Résultat attendu : fingerprint de 16 caractères
     */
    public function testGenerateFingerprint(): void
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
     * Vérifie le filtrage automatique
     * des données sensibles.
    /**
     * But : Vérifier que les données sensibles dans le context sont filtrées.
     *
     * Entrée : context.password = 'secret-password', context.token = 'secret-token'
     * Résultat attendu : context.password = '[FILTERED]', context.token = '[FILTERED]'
     */
    public function testFilterSensitiveData(): void

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
     * Vérifie la suppression des query strings
     * dans les URI.
     *
     * Cette normalisation garantit :
     * - un fingerprint stable,
     * - aucune fuite de secrets,
     * - une meilleure déduplication.
     */
    public function testNormalizeUriWithoutQueryString(): void
    /**
     * But : Vérifier que la query string est supprimée de l'URI.
     *
     * Entrée : request.uri = '/checkout?token=abc&user=42'
     * Résultat attendu : request.uri = '/checkout'
     */
    public function testNormalizeUriWithoutQueryString(): void
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
     * Vérifie que les URI numériques
     * produisent le même fingerprint.
     *
     * Exemple :
     * - /users/1
     * - /users/2
     *
     * doivent produire :
     * - le même regroupement.
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
     * Résultat attendu : externalId conservé identique
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
     * Résultat attendu : requestId = 'req_checkout_123'
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
     * But : Vérifier qu'une date client valide est normalisée sans erreur.
     *
     * Entrée : clientDate = '2026-01-01 10:00:00'
     * Résultat attendu : clientDate non null dans le payload normalisé
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
     * Vérifie les fallbacks par défaut
     * lorsqu'aucune donnée n'est fournie.
    /**
     * But : Vérifier que les valeurs fallback sont appliquées quand le payload est vide.
     *
     * Entrée : payload vide []
     * Résultat attendu : message='Unknown error', level='error', domain='unknown', env='prod' et autres valeurs par défaut
     */
    public function testFallbackValuesWhenFieldsMissing(): void
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
    /**
     * But : Vérifier que des URI avec segments numériques différents produisent le même fingerprint.
     *
     * Entrée : /users/1 et /users/999
     * Résultat attendu : Les deux fingerprints sont identiques
     */
    public function testFingerprintNormalizesNumericUris(): void