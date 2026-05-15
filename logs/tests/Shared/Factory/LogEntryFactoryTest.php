<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests nominaux de LogEntryFactory.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité
 * - prédictibilité
 * - cohérence des invariants
 * - robustesse des valeurs par défaut
 * - stabilité des warnings ingestion
 *
 * IMPORTANT :
 * ------------
 * Cette factory doit toujours produire :
 * - des LogEntry valides
 * - des données cohérentes
 * - des objets immutables
 * - des warnings ingestion stables
 */
final class LogEntryFactoryTest extends TestCase
{
    /**
     * But : Vérifier que la factory crée une entrée de log avec toutes les valeurs par défaut correctes.
     *
     * Entrée : Appel à LogEntryFactory::create() sans paramètres
     * Résultat attendu : Instance LogEntry avec message, domain, level, env, httpStatus, client, requestId, request, ip et fingerprint corrects
     */
    public function testItCreatesValidLogEntry(): void
    {
        $entry = LogEntryFactory::create();

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertSame(
            'Test log entry',
            $entry->getMessage(),
        );

        self::assertSame(
            'app',
            $entry->getDomain(),
        );

        self::assertSame(
            LogLevel::ERROR,
            $entry->getLevel(),
        );

        self::assertSame(
            Environment::Test,
            $entry->getEnvironment(),
        );

        self::assertSame(
            500,
            $entry
                ->getHttpStatus()
                ->value(),
        );

        self::assertSame(
            'phpunit',
            $entry
                ->getClient()
                ->value(),
        );

        self::assertSame(
            'req_phpunit_test',
            $entry
                ->getRequestId()
                ->value(),
        );

        self::assertSame(
            '/test',
            $entry
                ->getRequest()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'GET',
            $entry
                ->getRequest()
                ->method(),
        );

        self::assertSame(
            'PHPUnit',
            $entry
                ->getRequest()
                ->userAgent(),
        );

        self::assertSame(
            '127.0.0.1',
            $entry
                ->getIpAddress()
                ->value(),
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $entry
                ->getFingerprint()
                ->value(),
        );

        self::assertFalse(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que la factory accepte un message personnalisé.
     *
     * Entrée : message = 'Database failure'
     * Résultat attendu : entry->message() = 'Database failure'
     */
    public function testItCreatesCustomMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: 'Database failure',
        );

        self::assertSame(
            'Database failure',
            $entry->getMessage(),
        );
    }

    /**
     * But : Vérifier que les messages trop longs sont tronqués à 1 000 caractères.
     *
     * Entrée : message = 5 000 caractères 'A'
     * Résultat attendu : mb_strlen(message) = 1000, warning IngestionWarningType::MESSAGE_TRUNCATED présent
     */
    public function testItTruncatesHugeMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: str_repeat(
                'A',
                5000,
            ),
        );

        self::assertSame(
            1000,
            mb_strlen(
                $entry->getMessage(),
            ),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::MESSAGE_TRUNCATED,
        );
    }

    /**
     * But : Vérifier que LogEntryFactory::error() crée un log de niveau ERROR.
     *
     * Entrée : Appel à LogEntryFactory::error()
     * Résultat attendu : level = LogLevel::ERROR, isError() = true
     */
    public function testItCreatesErrorLog(): void
    {
        $entry = LogEntryFactory::error();

        self::assertSame(
            LogLevel::ERROR,
            $entry->getLevel(),
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    /**
     * But : Vérifier que LogEntryFactory::warning() crée un log de niveau WARNING.
     *
     * Entrée : Appel à LogEntryFactory::warning()
     * Résultat attendu : level = LogLevel::WARNING
     */
    public function testItCreatesWarningLog(): void
    {
        $entry = LogEntryFactory::warning();

        self::assertSame(
            LogLevel::WARNING,
            $entry->getLevel(),
        );
    }

    /**
     * But : Vérifier que LogEntryFactory::info() crée un log de niveau INFO.
     *
     * Entrée : Appel à LogEntryFactory::info()
     * Résultat attendu : level = LogLevel::INFO
     */
    public function testItCreatesInfoLog(): void
    {
        $entry = LogEntryFactory::info();

        self::assertSame(
            LogLevel::INFO,
            $entry->getLevel(),
        );
    }

    /**
     * But : Vérifier que many(5) crée exactement 5 entrées.
     *
     * Entrée : LogEntryFactory::many(5)
     * Résultat attendu : count = 5
     */
    public function testItCreatesManyEntries(): void
    {
        $entries = LogEntryFactory::many(5);

        self::assertCount(
            5,
            $entries,
        );
    }

    /**
     * But : Vérifier que deux appels consécutifs génèrent des IDs distincts.
     *
     * Entrée : Deux appels à LogEntryFactory::create()
     * Résultat attendu : entry1->id() !== entry2->id()
     */
    public function testItCreatesUniqueIds(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        self::assertNotSame(
            $entry1->getExternalId(),
            $entry2->getExternalId(),
        );
    }

    /**
     * But : Vérifier que deux messages différents génèrent des fingerprints distincts.
     *
     * Entrée : create(message='message-1') et create(message='message-2')
     * Résultat attendu : fingerprints différents
     */
    public function testItCreatesUniqueFingerprints(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'message-1',
        );

        $entry2 = LogEntryFactory::create(
            message: 'message-2',
        );

        self::assertNotSame(
            $entry1
                ->getFingerprint()
                ->value(),

            $entry2
                ->getFingerprint()
                ->value(),
        );
    }

    /**
     * But : Vérifier que l'ID fourni est utilisé tel quel.
     *
     * Entrée : id = 'custom-id'
     * Résultat attendu : entry->id() = 'custom-id'
     */
    public function testItUsesProvidedId(): void
    {
        $entry = LogEntryFactory::create(
            id: 'custom-id',
        );

        self::assertSame(
            'custom-id',
            $entry->getExternalId(),
        );
    }

    /**
     * But : Vérifier que le requestId fourni est utilisé tel quel.
     *
     * Entrée : requestId = 'req_checkout_42'
     * Résultat attendu : requestId()->value() = 'req_checkout_42'
     */
    public function testItUsesProvidedRequestId(): void
    {
        $entry = LogEntryFactory::create(
            requestId: 'req_checkout_42',
        );

        self::assertSame(
            'req_checkout_42',
            $entry
                ->getRequestId()
                ->value(),
        );
    }

    /**
     * But : Vérifier que le fingerprint fourni est utilisé tel quel.
     *
     * Entrée : fingerprint = 'abcdef1234567890'
     * Résultat attendu : fingerprint()->value() = 'abcdef1234567890'
     */
    public function testItUsesProvidedFingerprint(): void
    {
        $entry = LogEntryFactory::create(
            fingerprint: 'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $entry
                ->getFingerprint()
                ->value(),
        );
    }

    /**
     * But : Vérifier que le context fourni est stocké correctement.
     *
     * Entrée : context = ['userId' => 42]
     * Résultat attendu : context() = ['userId' => 42]
     */
    public function testItStoresContext(): void
    {
        $entry = LogEntryFactory::create(
            context: [
                'userId' => 42,
            ],
        );

        self::assertSame(
            [
                'userId' => 42,
            ],
            $entry->getContext(),
        );
    }

    /**
     * But : Vérifier que les données extra sont stockées correctement.
     *
     * Entrée : extra = ['memory' => '128MB']
     * Résultat attendu : extra() = ['memory' => '128MB']
     */
    public function testItStoresExtra(): void
    {
        $entry = LogEntryFactory::create(
            extra: [
                'memory' => '128MB',
            ],
        );

        self::assertSame(
            [
                'memory' => '128MB',
            ],
            $entry->getExtra(),
        );
    }

    /**
     * But : Vérifier que l'environnement personnalisé est stocké.
     *
     * Entrée : environment = Environment::Production
     * Résultat attendu : environment() = Environment::Production
     */
    public function testItCreatesCustomEnvironment(): void
    {
        $entry = LogEntryFactory::create(
            environment: Environment::Production,
        );

        self::assertSame(
            Environment::Production,
            $entry->getEnvironment(),
        );
    }

    /**
     * But : Vérifier que le domaine personnalisé est stocké.
     *
     * Entrée : domain = 'billing'
     * Résultat attendu : domain() = 'billing'
     */
    public function testItCreatesCustomDomain(): void
    {
        $entry = LogEntryFactory::create(
            domain: 'billing',
        );

        self::assertSame(
            'billing',
            $entry->getDomain(),
        );
    }

    /**
     * But : Vérifier que les informations de requête personnalisées sont stockées.
     *
     * Entrée : method='POST', uri='/api/logs', userAgent='Symfony HttpClient'
     * Résultat attendu : request()->method()='POST', uri='/api/logs', userAgent='Symfony HttpClient'
     */
    public function testItCreatesCustomRequest(): void
    {
        $entry = LogEntryFactory::create(
            method: 'POST',
            uri: '/api/logs',
            userAgent: 'Symfony HttpClient',
        );

        self::assertSame(
            'POST',
            $entry
                ->getRequest()
                ->method(),
        );

        self::assertSame(
            '/api/logs',
            $entry
                ->getRequest()
                ->uri()
                ->value(),
        );

        self::assertSame(
            'Symfony HttpClient',
            $entry
                ->getRequest()
                ->userAgent(),
        );
    }

    /**
     * But : Vérifier qu'une méthode HTTP invalide (trop longue) est normalisée.
     *
     * Entrée : method = str_repeat('POST', 50)
     * Résultat attendu : request()->method() est une méthode HTTP valide (GET, POST, PUT, etc.)
     */
    public function testItNormalizesHugeMethod(): void
    {
        $entry = LogEntryFactory::create(
            method: str_repeat(
                'POST',
                50,
            ),
        );

        self::assertContains(
            $entry
                ->getRequest()
                ->method(),
            [
                'GET',
                'POST',
                'PUT',
                'PATCH',
                'DELETE',
                'HEAD',
                'OPTIONS',
            ],
        );
    }

    /**
     * But : Vérifier que les User-Agent trop longs sont tronqués à 500 caractères.
     *
     * Entrée : userAgent = str_repeat('Mozilla', 500)
     * Résultat attendu : mb_strlen(userAgent) = 500, warning USER_AGENT_TRUNCATED présent
     */
    public function testItTruncatesHugeUserAgent(): void
    {
        $entry = LogEntryFactory::create(
            userAgent: str_repeat(
                'Mozilla',
                500,
            ),
        );

        self::assertSame(
            500,
            mb_strlen(
                $entry
                    ->getRequest()
                    ->userAgent(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::USER_AGENT_TRUNCATED,
        );
    }

    /**
     * But : Vérifier que l'IP personnalisée est stockée.
     *
     * Entrée : ip = '192.168.1.10'
     * Résultat attendu : ipAddress()->value() = '192.168.1.10'
     */
    public function testItCreatesCustomIp(): void
    {
        $entry = LogEntryFactory::create(
            ip: '192.168.1.10',
        );

        self::assertSame(
            '192.168.1.10',
            $entry
                ->getIpAddress()
                ->value(),
        );
    }

    /**
     * But : Vérifier que many() retourne [] quand le count est négatif.
     *
     * Entrée : LogEntryFactory::many(-5)
     * Résultat attendu : []
     */
    public function testManyReturnsEmptyArrayWhenNegative(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(-5),
        );
    }

    /**
     * But : Vérifier que many() retourne [] quand le count est zéro.
     *
     * Entrée : LogEntryFactory::many(0)
     * Résultat attendu : []
     */
    public function testManyReturnsEmptyArrayWhenZero(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(0),
        );
    }

    /**
     * But : Vérifier que many(250) retourne exactement 250 entrées.
     *
     * Entrée : LogEntryFactory::many(250)
     * Résultat attendu : count = 250
     */
    public function testManyCreatesRequestedAmount(): void
    {
        self::assertCount(
            250,
            LogEntryFactory::many(250),
        );
    }

    /**
     * But : Vérifier que many() génère des requestIds uniques pour chaque entrée.
     *
     * Entrée : LogEntryFactory::many(50)
     * Résultat attendu : 50 requestIds distincts
     */
    public function testManyCreatesUniqueRequestIds(): void
    {
        $entries = LogEntryFactory::many(
            50,
        );

        $requestIds = array_map(
            static fn (LogEntry $entry): string => $entry
                ->getRequestId()
                ->value(),
            $entries,
        );

        self::assertCount(
            50,
            array_unique(
                $requestIds,
            ),
        );
    }

    /**
     * But : Vérifier que les warnings d'ingestion sont stockés.
     *
     * Entrée : ingestionWarnings = [warningMessageTruncated(), warningInvalidIp()]
     * Résultat attendu : count(ingestionWarnings) = 2, hasIngestionWarnings() = true
     */
    public function testItStoresIngestionWarnings(): void
    {
        $entry = LogEntryFactory::create(
            ingestionWarnings: [
                LogEntryFactory::warningMessageTruncated(),
                LogEntryFactory::warningInvalidIp(),
            ],
        );

        self::assertCount(
            2,
            $entry->getIngestionWarnings(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que les warnings sont inclus dans la sérialisation toArray().
     *
     * Entrée : ingestionWarnings = [warningMessageTruncated()]
     * Résultat attendu : toArray()['ingestionWarnings'] est un tableau non vide
     */
    public function testItSerializesWarnings(): void
    {
        $entry = LogEntryFactory::create(
            ingestionWarnings: [
                LogEntryFactory::warningMessageTruncated(),
            ],
        );

        $data = $entry->toArray();

        self::assertArrayHasKey(
            'ingestionWarnings',
            $data,
        );

        self::assertCount(
            1,
            $data['ingestionWarnings'],
        );

        self::assertSame(
            IngestionWarningType::MESSAGE_TRUNCATED->value,
            $data['ingestionWarnings'][0]['type'],
        );
    }

    private function assertContainsWarningType(
        LogEntry $entry,
        IngestionWarningType $expected,
    ): void {
        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->getIngestionWarnings(),
        );

        self::assertContains(
            $expected->value,
            $types,
        );
    }
}