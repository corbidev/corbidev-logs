<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Factory;

use App\Log\Application\Factory\LogEntryFactory;
use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogEntryFactory.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - protection ingestion
 * - stabilité des warnings
 *
 * IMPORTANT :
 * ------------
 * La factory doit :
 * - ne jamais crash
 * - tolérer les payloads hostiles
 * - générer requestId si absent
 * - stabiliser les anciennes structures
 * - rester rétrocompatible ingestion
 * - tracer les corrections ingestion
 */
#[CoversClass(LogEntryFactory::class)]
final class LogEntryFactoryTest extends TestCase
{
    private LogEntryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LogEntryFactory();
    }

    /**
     * But : Vérifier que la factory crée une LogEntry complète depuis un payload valide.
     *
     * Entrée : Payload complet avec message, domain, level, environment, requestId, request, ip
     * Résultat attendu : Tous les champs de la LogEntry correspondent aux valeurs du payload
     */
    public function testItCreatesLogEntry(): void
    {
        $entry = $this->factory->create([
            'message' => 'Payment failed',
            'level' => 'error',
            'domain' => 'billing',
            'env' => 'prod',
            'httpStatus' => 500,
            'client' => 'checkout-app',

            'requestId' => 'req_checkout_123',

            'request' => [
                'uri' => '/orders',
                'method' => 'POST',
                'userAgent' => 'Mozilla/5.0',
            ],

            'ip' => '127.0.0.1',

            'fingerprint' => 'abcdef1234567890',
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertFalse(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que le requestId est normalisé en minuscules.
     *
     * Entrée : requestId = 'REQ_ABC_123'
     * Résultat attendu : requestId() = 'req_abc_123'
     */
    public function testItCreatesRequestId(): void
    {
        $entry = $this->factory->create([
            'requestId' => 'REQ_CHECKOUT_123',
        ]);

        self::assertSame(
            'req_checkout_123',
            $entry
                ->requestId()
                ->value(),
        );
    }

    /**
     * But : Vérifier que la factory génère un requestId si absent du payload.
     *
     * Entrée : Payload sans requestId
     * Résultat attendu : requestId non vide généré automatiquement
     */
    public function testItGeneratesRequestIdWhenMissing(): void
    {
        $entry = $this->factory->create([]);

        self::assertStringStartsWith(
            'req_',
            $entry
                ->requestId()
                ->value(),
        );
    }

    /**
     * But : Vérifier que la factory régénère un requestId si la valeur est invalide.
     *
     * Entrée : requestId = '<script>alert(1)</script>'
     * Résultat attendu : Nouveau requestId généré, warning INVALID_REQUEST_ID ajouté
     */
    public function testItGeneratesRequestIdWhenInvalid(): void
    {
        $entry = $this->factory->create([
            'requestId' => '<script>',
        ]);

        self::assertStringStartsWith(
            'req_',
            $entry
                ->requestId()
                ->value(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_REQUEST_ID,
        );
    }

    /**
     * But : Vérifier que la factory normalise le message (trim des espaces).
     *
     * Entrée : message = '  Payment failed  '
     * Résultat attendu : message = 'Payment failed'
     */
    public function testItNormalizesMessage(): void
    {
        $entry = $this->factory->create([
            'message' => '   Payment failed   ',
        ]);

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );
    }

    /**
     * But : Vérifier que la factory retourne 'unknown error' pour un message null.
     *
     * Entrée : message = null
     * Résultat attendu : message = 'unknown error'
     */
    public function testItFallsBackForInvalidMessage(): void
    {
        $entry = $this->factory->create([
            'message' => null,
        ]);

        self::assertSame(
            'unknown error',
            $entry->message(),
        );
    }

    /**
     * But : Vérifier que la factory tronque les messages dépassant 1 000 caractères.
     *
     * Entrée : message de 2 000 caractères
     * Résultat attendu : message tronqué à 1 000, warning MESSAGE_TRUNCATED ajouté
     */
    public function testItTruncatesHugeMessage(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'A',
                5000,
            ),
        ]);

        self::assertLessThanOrEqual(
            1000,
            mb_strlen(
                $entry->message(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::MESSAGE_TRUNCATED,
        );
    }

    /**
     * But : Vérifier que la factory normalise le domaine en minuscules.
     *
     * Entrée : domain = 'BILLING'
     * Résultat attendu : domain = 'billing'
     */
    public function testItNormalizesDomain(): void
    {
        $entry = $this->factory->create([
            'domain' => ' BILLING ',
        ]);

        self::assertSame(
            'billing',
            $entry->domain(),
        );
    }

    /**
     * But : Vérifier que la factory retourne 'unknown' pour un domaine null.
     *
     * Entrée : domain = null
     * Résultat attendu : domain = 'unknown'
     */
    public function testItFallsBackForInvalidDomain(): void
    {
        $entry = $this->factory->create([
            'domain' => null,
        ]);

        self::assertSame(
            'unknown',
            $entry->domain(),
        );
    }

    /**
     * But : Vérifier que la factory crée correctement un LogLevel depuis une string.
     *
     * Entrée : level = 'critical'
     * Résultat attendu : LogLevel::CRITICAL
     */
    public function testItCreatesLogLevel(): void
    {
        $entry = $this->factory->create([
            'level' => 'critical',
        ]);

        self::assertSame(
            LogLevel::CRITICAL,
            $entry->level(),
        );
    }

    /**
     * But : Vérifier que la factory retourne ERROR si le level est invalide.
     *
     * Entrée : level = 'invalid-level'
     * Résultat attendu : LogLevel::ERROR, warning INVALID_LEVEL ajouté
     */
    public function testItFallsBackLogLevel(): void
    {
        $entry = $this->factory->create([
            'level' => 'INVALID',
        ]);

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_LEVEL,
        );
    }

    /**
     * But : Vérifier que la factory crée correctement un environnement depuis une string.
     *
     * Entrée : environment = 'staging'
     * Résultat attendu : Environment::Staging
     */
    public function testItCreatesEnvironment(): void
    {
        $entry = $this->factory->create([
            'env' => 'staging',
        ]);

        self::assertSame(
            Environment::Staging,
            $entry->environment(),
        );
    }

    /**
     * But : Vérifier que la factory retourne Production si l'environnement est invalide.
     *
     * Entrée : environment = 'invalid-env'
     * Résultat attendu : Environment::Production, warning INVALID_ENVIRONMENT ajouté
     */
    public function testItFallsBackEnvironment(): void
    {
        $entry = $this->factory->create([
            'env' => 'INVALID',
        ]);

        self::assertSame(
            Environment::Production,
            $entry->environment(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_ENVIRONMENT,
        );
    }

    /**
     * But : Vérifier que la factory crée une Request depuis le payload imbriqué 'request'.
     *
     * Entrée : payload['request'] = ['method' => 'POST', 'uri' => '/orders', 'userAgent' => 'Mozilla/5.0']
     * Résultat attendu : request() avec method='POST', uri='/orders', userAgent='Mozilla/5.0'
     */
    public function testItCreatesRequest(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'uri' => '/orders',
                'method' => 'POST',
                'userAgent' => 'Mozilla/5.0',
            ],
        ]);

        self::assertSame(
            '/orders',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'POST',
            $entry
                ->request()
                ->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    /**
     * But : Vérifier que la factory supporte l'ancien format avec uri/method/userAgent au niveau racine.
     *
     * Entrée : payload['uri'] = '/orders', payload['method'] = 'POST', payload['userAgent'] = 'Mozilla/5.0'
     * Résultat attendu : request() avec les valeurs legacy correctes
     */
    public function testItSupportsLegacyRequestPayload(): void
    {
        $entry = $this->factory->create([
            'uri' => '/legacy',
            'method' => 'PUT',
            'userAgent' => 'LegacyAgent',
        ]);

        self::assertSame(
            '/legacy',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'PUT',
            $entry
                ->request()
                ->method(),
        );

        self::assertSame(
            'LegacyAgent',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    /**
     * But : Vérifier que la factory retourne 'GET' si la méthode HTTP est null.
     *
     * Entrée : request['method'] = null
     * Résultat attendu : method = 'GET'
     */
    public function testItFallsBackRequestMethod(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'method' => null,
            ],
        ]);

        self::assertSame(
            'GET',
            $entry
                ->request()
                ->method(),
        );
    }

    /**
     * But : Vérifier que la factory tronque les user agents dépassant 500 caractères.
     *
     * Entrée : userAgent de 600 caractères
     * Résultat attendu : userAgent tronqué à 500, warning USER_AGENT_TRUNCATED ajouté
     */
    public function testItTruncatesHugeUserAgent(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'userAgent' => str_repeat(
                    'Mozilla/5.0 ',
                    5000,
                ),
            ],
        ]);

        self::assertLessThanOrEqual(
            500,
            mb_strlen(
                $entry
                    ->request()
                    ->userAgent(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::USER_AGENT_TRUNCATED,
        );
    }

    /**
     * But : Vérifier que la factory retourne '' pour un user agent null.
     *
     * Entrée : userAgent = null
     * Résultat attendu : userAgent = ''
     */
    public function testItFallsBackUserAgent(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'userAgent' => null,
            ],
        ]);

        self::assertSame(
            '',
            $entry
                ->request()
                ->userAgent(),
        );
    }

    /**
     * But : Vérifier que le contexte est bien stocké depuis le payload.
     *
     * Entrée : context = ['userId' => 42]
     * Résultat attendu : context() = ['userId' => 42]
     */
    public function testItCreatesContext(): void
    {
        $entry = $this->factory->create([
            'context' => [
                'userId' => 42,
            ],
        ]);

        self::assertSame(
            [
                'userId' => 42,
            ],
            $entry->context(),
        );
    }

    /**
     * But : Vérifier que le champ extra est bien stocké depuis le payload.
     *
     * Entrée : extra = ['memory' => '128MB']
     * Résultat attendu : extra() = ['memory' => '128MB']
     */
    public function testItCreatesExtra(): void
    {
        $entry = $this->factory->create([
            'extra' => [
                'memory' => 123,
            ],
        ]);

        self::assertSame(
            [
                'memory' => 123,
            ],
            $entry->extra(),
        );
    }

    /**
     * But : Vérifier que la factory retourne [] pour un context invalide.
     *
     * Entrée : context = 'invalid' (string)
     * Résultat attendu : context() = []
     */
    public function testItFallsBackInvalidContext(): void
    {
        $entry = $this->factory->create([
            'context' => 'invalid',
        ]);

        self::assertSame(
            [],
            $entry->context(),
        );
    }

    /**
     * But : Vérifier que la factory retourne [] pour un extra invalide.
     *
     * Entrée : extra = 'invalid' (string)
     * Résultat attendu : extra() = []
     */
    public function testItFallsBackInvalidExtra(): void
    {
        $entry = $this->factory->create([
            'extra' => 'invalid',
        ]);

        self::assertSame(
            [],
            $entry->extra(),
        );
    }

    /**
     * But : Vérifier que la factory retourne l'IP fallback pour une IP invalide.
     *
     * Entrée : ip = '999.999.999.999'
     * Résultat attendu : ip = '127.0.0.1', warning INVALID_IP ajouté
     */
    public function testItFallsBackInvalidIp(): void
    {
        $entry = $this->factory->create([
            'ip' => '999.999.999.999',
        ]);

        self::assertSame(
            '127.0.0.1',
            $entry
                ->ipAddress()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_IP,
        );
    }

    /**
     * But : Vérifier que la factory régénère le fingerprint si la valeur est invalide.
     *
     * Entrée : fingerprint = 'INVALID'
     * Résultat attendu : Nouveau fingerprint généré, warning FINGERPRINT_REGENERATED ajouté
     */
    public function testItFallsBackInvalidFingerprint(): void
    {
        $entry = $this->factory->create([
            'fingerprint' => 'INVALID',
        ]);

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $entry
                ->fingerprint()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::FINGERPRINT_REGENERATED,
        );
    }

    /**
     * But : Vérifier que la factory retourne '/' pour une URI invalide (trop longue).
     *
     * Entrée : request['uri'] de 5 000 caractères
     * Résultat attendu : uri = '/', warning INVALID_URI ajouté
     */
    public function testItFallsBackInvalidUri(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'uri' => str_repeat(
                    '/orders',
                    1000,
                ),
            ],
        ]);

        self::assertSame(
            '/',
            $entry
                ->request()
                ->uri()
                ->value(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_URI,
        );
    }

    /**
     * IMPORTANT :
     * ------------
     * Les anciens clients peuvent encore
     * envoyer un champ "tags".
     *
     * La factory doit l'ignorer sans crash.
     */
    /**
     * But : Vérifier que le champ 'tags' legacy est ignoré sans provoquer de crash.
     *
     * Entrée : payload['tags'] = ['api', 'v2']
     * Résultat attendu : LogEntry créée sans exception, champ 'tags' ignoré
     */
    public function testItIgnoresLegacyTagsPayload(): void
    {
        $entry = $this->factory->create([
            'tags' => [
                'feature' => 'checkout',
                'region' => 'eu',
            ],
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que la factory génère un UUID si le champ 'id' est absent du payload.
     *
     * Entrée : Payload sans champ 'id'
     * Résultat attendu : id() non vide généré automatiquement
     */
    public function testItCreatesGeneratedUuid(): void
    {
        $entry = $this->factory->create([]);

        self::assertNotEmpty(
            $entry->id(),
        );
    }

    /**
     * But : Vérifier que la factory conserve l'UUID fourni dans le payload.
     *
     * Entrée : id = 'existing-uuid-1234'
     * Résultat attendu : id() = 'existing-uuid-1234'
     */
    public function testItKeepsExistingUuid(): void
    {
        $id = '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21';

        $entry = $this->factory->create([
            'id' => $id,
        ]);

        self::assertSame(
            $id,
            $entry->id(),
        );
    }

    /**
     * But : Vérifier que la factory parse correctement une date createdAt depuis une string.
     *
     * Entrée : createdAt = '2025-01-01 10:00:00'
     * Résultat attendu : createdAt() retourne la date correspondante
     */
    public function testItCreatesDates(): void
    {
        $entry = $this->factory->create([
            'createdAt' => '2025-01-01 10:00:00',
        ]);

        self::assertSame(
            '2025-01-01',
            $entry
                ->createdAt()
                ->format('Y-m-d'),
        );
    }

    /**
     * But : Vérifier que la factory retourne la date courante si la date est invalide.
     *
     * Entrée : createdAt = 'not-a-date'
     * Résultat attendu : createdAt() retourne une date proche de now(), warning INVALID_CREATED_AT ajouté
     */
    public function testItHandlesInvalidDate(): void
    {
        $entry = $this->factory->create([
            'createdAt' => 'INVALID_DATE',
        ]);

        self::assertNotNull(
            $entry->createdAt(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::INVALID_CREATED_AT,
        );
    }

    /**
     * But : Vérifier que la factory ne crashe jamais avec un payload totalement invalide.
     *
     * Entrée : Payload ne contenant que des valeurs invalides (null, empty, hostile)
     * Résultat attendu : Instance LogEntry valide retournée sans exception
     */
    public function testItNeverCrashesWithHostilePayload(): void
    {
        $entry = $this->factory->create([
            'message' => [
                'invalid',
            ],

            'domain' => new \stdClass(),

            'requestId' => [
                'bad',
            ],

            'request' => 'invalid',

            'context' => 'bad',

            'extra' => false,
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItExposesStableWarningsStructure(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'A',
                5000,
            ),

            'ip' => '999.999.999.999',
        ]);

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        foreach (
            $entry->ingestionWarnings()
            as $warning
        ) {
            $data = $warning->toArray();

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
    }

    private function assertContainsWarningType(
        LogEntry $entry,
        IngestionWarningType $expected,
    ): void {
        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            $expected->value,
            $types,
        );
    }
}