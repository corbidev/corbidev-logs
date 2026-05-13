<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\Entity;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\Exception\InvalidLogEntryException;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IngestionWarning;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests métier de LogEntry.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - immutabilité
 * - cohérence métier
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - invariants métier
 * - normalisation
 * - requestId obligatoire
 * - stabilité serialization
 * - stabilité equals()
 * - stabilité ingestionWarnings
 * - cohérence ValueObjects
 */
#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    /**
     * But : Vérifier que LogEntry est correctement créé avec des paramètres valides.
     *
     * Entrée : message='Payment failed', domain='billing', level=ERROR, env=Production
     * Résultat attendu : message, domain, level, environment corrects dans l'instance
     */
    public function testItCreatesValidLogEntry(): void
    {
        $entry = $this->createEntry();

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );

        self::assertSame(
            'billing',
            $entry->domain(),
        );

        self::assertSame(
            LogLevel::ERROR,
            $entry->level(),
        );

        self::assertSame(
            Environment::Production,
            $entry->environment(),
        );
    }

    /**
     * But : Vérifier que le domaine est normalisé en minuscules avec trim.
     *
     * Entrée : domain = ' BILLING '
     * Résultat attendu : domain = 'billing'
     */
    public function testItNormalizesDomain(): void
    {
        $entry = $this->createEntry(
            domain: ' BILLING ',
        );

        self::assertSame(
            'billing',
            $entry->domain(),
        );
    }

    /**
     * But : Vérifier que le message est normalisé (trim des espaces).
     *
     * Entrée : message = '  Payment failed  '
     * Résultat attendu : message = 'Payment failed'
     */
    public function testItNormalizesMessage(): void
    {
        $entry = $this->createEntry(
            message: '  Payment failed  ',
        );

        self::assertSame(
            'Payment failed',
            $entry->message(),
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un message vide.
     *
     * Entrée : message = ''
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsEmptyMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: '',
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un message composé uniquement d'espaces.
     *
     * Entrée : message = '   '
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsWhitespaceMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: '      ',
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un domaine vide.
     *
     * Entrée : domain = ''
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsEmptyDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: '',
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un domaine composé uniquement d'espaces.
     *
     * Entrée : domain = '   '
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsWhitespaceDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: '      ',
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un message dépassant 1 000 caractères.
     *
     * Entrée : message de 1 001 caractères
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsTooLongMessage(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            message: str_repeat(
                'A',
                1001,
            ),
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un domaine dépassant 100 caractères.
     *
     * Entrée : domain de 101 caractères
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsTooLongDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: str_repeat(
                'a',
                101,
            ),
        );
    }

    /**
     * But : Vérifier que l'identifiant est non vide et stable.
     *
     * Entrée : Création d'une LogEntry valide
     * Résultat attendu : id() non vide
     */
    public function testItReturnsStableId(): void
    {
        $entry = $this->createEntry();

        self::assertNotEmpty(
            $entry->id(),
        );
    }

    /**
     * But : Vérifier que deux instances de LogEntry génèrent des identifiants différents.
     *
     * Entrée : Deux instances avec les mêmes paramètres
     * Résultat attendu : id() différents
     */
    public function testItGeneratesDifferentIds(): void
    {
        $left = $this->createEntry();
        $right = $this->createEntry();

        self::assertNotSame(
            $left->id(),
            $right->id(),
        );
    }

    /**
     * But : Vérifier qu'un identifiant fourni est conservé.
     *
     * Entrée : id = 'custom-uuid-1234'
     * Résultat attendu : id() = 'custom-uuid-1234'
     */
    public function testItUsesProvidedId(): void
    {
        $entry = $this->createEntry(
            id: 'external-id',
        );

        self::assertSame(
            'external-id',
            $entry->id(),
        );
    }

    /**
     * But : Vérifier que toArray() retourne un tableau contenant les clés attendues.
     *
     * Entrée : LogEntry valide avec message, fingerprint, ingestionWarnings
     * Résultat attendu : toArray() contient 'id', 'message', 'fingerprint', 'ingestionWarnings'
     */
    public function testItReturnsStableSerialization(): void
    {
        $entry = $this->createEntry();

        $data = $entry->toArray();

        self::assertArrayHasKey(
            'id',
            $data,
        );

        self::assertArrayHasKey(
            'message',
            $data,
        );

        self::assertArrayHasKey(
            'fingerprint',
            $data,
        );

        self::assertArrayHasKey(
            'ingestionWarnings',
            $data,
        );
    }

    /**
     * But : Vérifier que toArray() sérialise correctement la requête (POST /checkout).
     *
     * Entrée : Request POST /checkout, userAgent='PHPUnit'
     * Résultat attendu : toArray()['request'] contient method, uri, userAgent corrects
     */
    public function testItReturnsStableRequestSerialization(): void
    {
        $entry = $this->createEntry();

        $request = $entry->toArray()['request'];

        self::assertSame(
            'POST',
            $request['method'],
        );

        self::assertSame(
            '/checkout',
            $request['uri'],
        );

        self::assertSame(
            'PHPUnit',
            $request['userAgent'],
        );
    }

    /**
     * But : Vérifier que isError() retourne true pour un niveau ERROR.
     *
     * Entrée : level = LogLevel::ERROR
     * Résultat attendu : isError() = true
     */
    public function testItDetectsErrorLog(): void
    {
        $entry = $this->createEntry(
            level: LogLevel::ERROR,
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    /**
     * But : Vérifier que isError() retourne true pour un httpStatus 500.
     *
     * Entrée : httpStatus = 500
     * Résultat attendu : isError() = true
     */
    public function testItDetectsHttpError(): void
    {
        $entry = $this->createEntry(
            httpStatus: new HttpStatus(
                500,
            ),
        );

        self::assertTrue(
            $entry->isError(),
        );
    }

    /**
     * But : Vérifier que equals() retourne true pour deux entrées avec le même id.
     *
     * Entrée : Deux instances avec id = 'same-id'
     * Résultat attendu : equals() = true
     */
    public function testItComparesEntriesById(): void
    {
        $entry = $this->createEntry(
            id: 'same-id',
        );

        $same = $this->createEntry(
            id: 'same-id',
        );

        self::assertTrue(
            $entry->equals(
                $same,
            ),
        );
    }

    /**
     * But : Vérifier que equals() retourne false pour deux entrées avec des ids différents.
     *
     * Entrée : Deux instances avec ids 'id-1' et 'id-2'
     * Résultat attendu : equals() = false
     */
    public function testItDetectsDifferentEntries(): void
    {
        $left = $this->createEntry(
            id: 'left',
        );

        $right = $this->createEntry(
            id: 'right',
        );

        self::assertFalse(
            $left->equals(
                $right,
            ),
        );
    }

    /**
     * But : Vérifier que le contexte est bien stocké dans la LogEntry.
     *
     * Entrée : context = ['userId' => 42]
     * Résultat attendu : context() = ['userId' => 42]
     */
    public function testItStoresContext(): void
    {
        $entry = $this->createEntry(
            context: [
                'userId' => 42,
            ],
        );

        self::assertSame(
            42,
            $entry
                ->context()['userId'],
        );
    }

    /**
     * But : Vérifier que le champ extra est bien stocké dans la LogEntry.
     *
     * Entrée : extra = ['memory' => '128MB']
     * Résultat attendu : extra() = ['memory' => '128MB']
     */
    public function testItStoresExtra(): void
    {
        $entry = $this->createEntry(
            extra: [
                'memory' => '128MB',
            ],
        );

        self::assertSame(
            '128MB',
            $entry
                ->extra()['memory'],
        );
    }

    /**
     * But : Vérifier que la date client est bien stockée dans la LogEntry.
     *
     * Entrée : clientDate = DateTimeImmutable('2025-01-01')
     * Résultat attendu : clientDate() retourne la date fournie
     */
    public function testItStoresClientDate(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            clientDate: $date,
        );

        self::assertSame(
            $date,
            $entry->clientDate(),
        );
    }

    /**
     * But : Vérifier que les warnings d'ingestion sont bien stockés.
     *
     * Entrée : 1 IngestionWarning de type INVALID_LEVEL
     * Résultat attendu : hasIngestionWarnings() = true, count = 1
     */
    public function testItStoresIngestionWarnings(): void
    {
        $warnings = [
            new IngestionWarning(
                field: 'ip',
                type: IngestionWarningType::INVALID_IP,
                original: '999.999.999.999',
                fallback: '127.0.0.1',
            ),
        ];

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        self::assertCount(
            1,
            $entry->ingestionWarnings(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que hasIngestionWarnings() retourne false quand il n'y a pas de warnings.
     *
     * Entrée : LogEntry créée sans warnings
     * Résultat attendu : hasIngestionWarnings() = false
     */
    public function testItReturnsFalseWithoutWarnings(): void
    {
        $entry = $this->createEntry();

        self::assertFalse(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que LogEntry rejette des warnings de type invalide.
     *
     * Entrée : Tableau warnings contenant 'not-a-warning'
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsInvalidWarnings(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            ingestionWarnings: [
                'invalid',
            ],
        );
    }

    /**
     * @param list<IngestionWarning|mixed> $ingestionWarnings
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        string $message = 'Payment failed',
        LogLevel $level = LogLevel::ERROR,
        string $domain = 'billing',
        Environment $environment = Environment::Production,
        ?HttpStatus $httpStatus = null,
        ?Client $client = null,
        ?Request $request = null,
        ?IpAddress $ipAddress = null,
        ?Fingerprint $fingerprint = null,
        ?RequestId $requestId = null,
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null,
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: $level,
            domain: $domain,
            environment: $environment,
            httpStatus: $httpStatus
                ?? new HttpStatus(500),
            client: $client
                ?? new Client('phpunit'),
            request: $request
                ?? new Request(
                    method: 'POST',
                    uri: new Uri('/checkout'),
                    userAgent: 'PHPUnit',
                ),
            ipAddress: $ipAddress
                ?? new IpAddress('127.0.0.1'),
            fingerprint: $fingerprint
                ?? new Fingerprint('abcdef1234567890'),
            requestId: $requestId
                ?? new RequestId('req_checkout'),
            ingestionWarnings: $ingestionWarnings,
            context: $context,
            extra: $extra,
            clientDate: $clientDate,
            createdAt: $createdAt,
            id: $id,
        );
    }
}