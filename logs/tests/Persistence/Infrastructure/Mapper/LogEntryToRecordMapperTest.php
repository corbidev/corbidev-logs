<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Infrastructure\Mapper;

use App\Log\Domain\Entity\LogEntry;
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
use App\Persistence\Infrastructure\Entity\LogRecord;
use App\Persistence\Infrastructure\Mapper\LogEntryToRecordMapper;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogEntryToRecordMapper.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité mapping
 * - robustesse persistence
 * - sanitation SQL
 * - cohérence Domain → Infrastructure
 * - stabilité JSON
 * - stabilité UTF-8
 * - robustesse ingestionWarnings
 *
 * IMPORTANT :
 * ------------
 * Ce mapper ne doit jamais :
 * - modifier les invariants métier
 * - recalculer le fingerprint
 * - throw sur payload hostile
 * - perdre des données métier valides
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - mapping complet
 * - sanitation SQL
 * - bornes JSON
 * - normalisation profondeur
 * - stabilité persistence
 * - compatibilité ingestionWarnings
 */
#[CoversClass(LogEntryToRecordMapper::class)]
final class LogEntryToRecordMapperTest extends TestCase
{
    private LogEntryToRecordMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new LogEntryToRecordMapper();
    }

    /**
     * But : Vérifier que map() retourne une instance de LogRecord.
     *
     * Entrée : projectId=42, LogEntry standard
     * Résultat attendu : Retourne un LogRecord valide
     */
    public function testItMapsLogEntry(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertInstanceOf(
            LogRecord::class,
            $record,
        );
    }

    /**
     * But : Vérifier que l'ID externe du LogEntry est copié dans LogRecord.
     *
     * Entrée : LogEntry avec id() généré
     * Résultat attendu : getExternalId() = $entry->id()
     */
    public function testItMapsExternalId(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $entry->getExternalId(),
            $record->getExternalId(),
        );
    }

    /**
     * But : Vérifier que le projectId entier est converti en string dans LogRecord.
     *
     * Entrée : projectId=999
     * Résultat attendu : getProjectId() = '999'
     */
    public function testItMapsProjectId(): void
    {
        $record = $this->mapper->map(
            999,
            $this->createEntry(),
        );

        self::assertSame(
            '999',
            $record->getProjectId(),
        );
    }

    /**
     * But : Vérifier que le requestId est correctement mappé depuis le LogEntry.
     *
     * Entrée : RequestId('req_checkout_123')
     * Résultat attendu : getRequestId() = 'req_checkout_123'
     */
    public function testItMapsRequestId(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'req_checkout_123',
            $record->getRequestId(),
        );
    }

    /**
     * But : Vérifier que le fingerprint est correctement mappé depuis le LogEntry.
     *
     * Entrée : Fingerprint('abcdef1234567890')
     * Résultat attendu : getFingerprint() = 'abcdef1234567890'
     */
    public function testItMapsFingerprint(): void
    {
        $entry = $this->createEntry();

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'abcdef1234567890',
            $record->getFingerprint(),
        );
    }

    /**
     * But : Vérifier que le niveau de log est mappé en string dans LogRecord.
     *
     * Entrée : LogLevel::ERROR
     * Résultat attendu : getLevel() = 'error'
     */
    public function testItMapsLevel(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'error',
            $record->getLevel(),
        );
    }

    /**
     * But : Vérifier que le code HTTP est mappé en entier dans LogRecord.
     *
     * Entrée : HttpStatus(500)
     * Résultat attendu : getHttpStatus() = 500
     */
    public function testItMapsHttpStatus(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            500,
            $record->getHttpStatus(),
        );
    }

    /**
     * But : Vérifier que le domaine métier est mappé dans LogRecord.
     *
     * Entrée : domain='billing'
     * Résultat attendu : getDomain() = 'billing'
     */
    public function testItMapsDomain(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'billing',
            $record->getDomain(),
        );
    }

    /**
     * But : Vérifier que l'URI est mappée dans LogRecord.
     *
     * Entrée : Uri('/orders')
     * Résultat attendu : getUri() = '/orders'
     */
    public function testItMapsUri(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            '/orders',
            $record->getUri(),
        );
    }

    /**
     * But : Vérifier que la méthode HTTP est mappée dans LogRecord.
     *
     * Entrée : method='POST'
     * Résultat attendu : getMethod() = 'POST'
     */
    public function testItMapsMethod(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'POST',
            $record->getMethod(),
        );
    }

    /**
     * But : Vérifier que le user-agent est mappé dans LogRecord.
     *
     * Entrée : userAgent='Mozilla/5.0'
     * Résultat attendu : getUserAgent() = 'Mozilla/5.0'
     */
    public function testItMapsUserAgent(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $record->getUserAgent(),
        );
    }

    /**
     * But : Vérifier que l'environnement est mappé en string dans LogRecord.
     *
     * Entrée : Environment::Production
     * Résultat attendu : getEnv() = 'prod'
     */
    public function testItMapsEnvironment(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'prod',
            $record->getEnv(),
        );
    }

    /**
     * But : Vérifier que le client est mappé dans LogRecord.
     *
     * Entrée : Client('checkout-app')
     * Résultat attendu : getClient() = 'checkout-app'
     */
    public function testItMapsClient(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'checkout-app',
            $record->getClient(),
        );
    }

    /**
     * But : Vérifier que le message est mappé dans LogRecord.
     *
     * Entrée : message='Payment failed'
     * Résultat attendu : getMessage() = 'Payment failed'
     */
    public function testItMapsMessage(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            'Payment failed',
            $record->getMessage(),
        );
    }

    /**
     * But : Vérifier que le contexte est mappé dans LogRecord après normalisation.
     *
     * Entrée : context=['userId' => 42]
     * Résultat attendu : getContextJson() = ['userId' => 42]
     */
    public function testItMapsContextJson(): void
    {
        $entry = $this->createEntry(
            context: [
                'userId' => 42,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            [
                'userId' => 42,
            ],
            $record->getContextJson(),
        );
    }

    /**
     * But : Vérifier que les données extra sont mappées dans LogRecord.
     *
     * Entrée : extra=['memory' => 123]
     * Résultat attendu : getExtraJson() = ['memory' => 123]
     */
    public function testItMapsExtraJson(): void
    {
        $entry = $this->createEntry(
            extra: [
                'memory' => 123,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            [
                'memory' => 123,
            ],
            $record->getExtraJson(),
        );
    }

    /**
     * But : Vérifier que la date de création est mappée dans LogRecord.
     *
     * Entrée : createdAt=new DateTimeImmutable()
     * Résultat attendu : getCreatedAt() retourne la même instance
     */
    public function testItMapsCreatedAt(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            createdAt: $date,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $date,
            $record->getCreatedAt(),
        );
    }

    /**
     * But : Vérifier que la date client est mappée dans LogRecord.
     *
     * Entrée : clientDate=new DateTimeImmutable()
     * Résultat attendu : getClientDate() retourne la même instance
     */
    public function testItMapsClientDate(): void
    {
        $date = new DateTimeImmutable();

        $entry = $this->createEntry(
            clientDate: $date,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            $date,
            $record->getClientDate(),
        );
    }

    /**
     * But : Vérifier que l'adresse IP est mappée dans LogRecord.
     *
     * Entrée : IpAddress('127.0.0.1')
     * Résultat attendu : getIp() = '127.0.0.1'
     */
    public function testItMapsIp(): void
    {
        $record = $this->mapper->map(
            42,
            $this->createEntry(),
        );

        self::assertSame(
            '127.0.0.1',
            $record->getIp(),
        );
    }

    /**
     * But : Vérifier que les avertissements d'ingestion sont mappés dans LogRecord.
     *
     * Entrée : Un IngestionWarning de type INVALID_MESSAGE
     * Résultat attendu : getIngestionWarningsJson() contient 1 élément
     */
    public function testItMapsIngestionWarnings(): void
    {
        $entry = $this->createEntry(
            ingestionWarnings: [
                new IngestionWarning(
                    type: IngestionWarningType::INVALID_MESSAGE,
                    field: 'message',
                    original: null,
                    fallback: 'unknown error',
                ),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertCount(
            1,
            $record->getIngestionWarningsJson(),
        );
    }

    /**
     * But : Vérifier que les caractères nuls dans les clés/valeurs de contexte sont supprimés.
     *
     * Entrée : context=["bad\0key" => "value\0with\0null"]
     * Résultat attendu : Clé 'badkey' avec valeur 'valuewithnull'
     */
    public function testItSanitizesControlCharacters(): void
    {
        $entry = $this->createEntry(
            context: [
                "bad\0key" => "value\0with\0null",
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertArrayHasKey(
            'badkey',
            $record->getContextJson(),
        );

        self::assertSame(
            'valuewithnull',
            $record
                ->getContextJson()['badkey'],
        );
    }

    /**
     * But : Vérifier que les objets dans le contexte sont convertis en représentation string.
     *
     * Entrée : context=['object' => new \stdClass()]
     * Résultat attendu : getContextJson()['object'] = '[object:stdClass]'
     */
    public function testItConvertsObjects(): void
    {
        $entry = $this->createEntry(
            context: [
                'object' => new \stdClass(),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            '[object:stdClass]',
            $record
                ->getContextJson()['object'],
        );
    }

    /**
     * But : Vérifier que les ressources PHP dans le contexte sont converties en '[resource]'.
     *
     * Entrée : context=['resource' => fopen('php://memory', 'r')]
     * Résultat attendu : getContextJson()['resource'] = '[resource]'
     */
    public function testItConvertsResources(): void
    {
        $resource = fopen(
            'php://memory',
            'r',
        );

        self::assertIsResource(
            $resource,
        );

        $entry = $this->createEntry(
            context: [
                'resource' => $resource,
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        fclose(
            $resource,
        );

        self::assertSame(
            '[resource]',
            $record
                ->getContextJson()['resource'],
        );
    }

    /**
     * But : Vérifier que les contextes imbriqués trop profondément sont tronqués.
     *
     * Entrée : Tableau imbriqué à 6 niveaux de profondeur
     * Résultat attendu : '__truncated__' = 'max_depth_reached' au niveau 5
     */
    public function testItNormalizesDeepContext(): void
    {
        $entry = $this->createEntry(
            context: [
                'a' => [
                    'b' => [
                        'c' => [
                            'd' => [
                                'e' => [
                                    'f' => 'overflow',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            'max_depth_reached',
            $record
                ->getContextJson()['a']['b']['c']['d']['e']['__truncated__'],
        );
    }

    /**
     * But : Vérifier que le contexte est limité à 50 clés maximum avec indicateur de troncature.
     *
     * Entrée : Tableau de 5 000 clés
     * Résultat attendu : getContextJson() ≤ 51 éléments, contient '__truncated__'
     */
    public function testItLimitsHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key-' . $i] = $i;
        }

        $entry = $this->createEntry(
            context: $context,
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertLessThanOrEqual(
            51,
            count(
                $record->getContextJson(),
            ),
        );

        self::assertArrayHasKey(
            '__truncated__',
            $record->getContextJson(),
        );
    }

    /**
     * But : Vérifier que les valeurs de contexte trop longues sont tronquées à 1 000 caractères.
     *
     * Entrée : context=['huge' => str_repeat('A', 10000)]
     * Résultat attendu : mb_strlen(getContextJson()['huge']) = 1000
     */
    public function testItTruncatesHugeStrings(): void
    {
        $entry = $this->createEntry(
            context: [
                'huge' => str_repeat(
                    'A',
                    10000,
                ),
            ],
        );

        $record = $this->mapper->map(
            42,
            $entry,
        );

        self::assertSame(
            1000,
            mb_strlen(
                $record
                    ->getContextJson()['huge'],
            ),
        );
    }

    /**
     * But : Vérifier que le Domain rejette un message trop long avant d'atteindre le mapper.
     *
     * Entrée : message=str_repeat('A', 10000)
     * Résultat attendu : \Throwable lancé lors de la création du LogEntry
     */
    public function testItRejectsHugeStringsAtDomainLevel(): void
    {
        /**
         * IMPORTANT :
         * ------------
         * Le mapper ne doit jamais recevoir
         * un LogEntry invalide.
         *
         * Les limites sont garanties
         * par le Domain.
         */

        $this->expectException(
            \Throwable::class,
        );

        $this->createEntry(
            message: str_repeat(
                'A',
                10000,
            ),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     * @param list<IngestionWarning> $ingestionWarnings
     */
    private function createEntry(
        string $message = 'Payment failed',
        array $context = [],
        array $extra = [],
        array $ingestionWarnings = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
    ): LogEntry {
        return new LogEntry(
            message: $message,

            level: LogLevel::ERROR,

            domain: 'billing',

            environment: Environment::Production,

            httpStatus: new HttpStatus(
                500,
            ),

            client: new Client(
                'checkout-app',
            ),

            requestId: new RequestId(
                'req_checkout_123',
            ),

            request: new Request(
                uri: new Uri('/orders'),
                method: 'POST',
                userAgent: 'Mozilla/5.0',
            ),

            ipAddress: new IpAddress(
                '127.0.0.1',
            ),

            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),

            context: $context,

            extra: $extra,

            ingestionWarnings: $ingestionWarnings,

            clientDate: $clientDate,

            createdAt: $createdAt,
        );
    }
}
