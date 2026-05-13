<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Factory;

use App\Log\Application\Factory\LogEntryFactory;
use App\Log\Domain\Entity\LogEntry;
use App\Log\Enum\IngestionWarningType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Crash tests critiques de LogEntryFactory.
 *
 * OBJECTIFS :
 * -----------
 * - garantir robustesse ingestion
 * - garantir absence de crash
 * - garantir stabilité mémoire
 * - tester payloads hostiles
 * - tester payloads legacy
 * - tester structures invalides
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - requestId invalide
 * - request payload invalide
 * - binary payloads
 * - invalid UTF-8
 * - gros payloads
 * - gros tableaux
 * - structures hostiles
 * - ressources PHP
 * - compatibilité legacy
 * - ingestionWarnings
 *
 * IMPORTANT :
 * ------------
 * La factory ingestion :
 * - ne doit presque jamais throw
 * - doit corriger les données hostiles
 * - doit tracer les corrections
 */
#[CoversClass(LogEntryFactory::class)]
final class LogEntryFactoryCrashTest extends TestCase
{
    private LogEntryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LogEntryFactory();
    }

    /**
     * But : Vérifier que la factory ne lève jamais d'exception avec des payloads hostiles.
     *
     * Entrée : Payloads variés (null, stdClass, binaire, UTF-8 invalide, ressource PHP, XSS, SQL injection)
     * Résultat attendu : Instance LogEntry valide retournée pour chaque payload, aucune exception
     */
    public function testItNeverThrowsWithHostilePayloads(): void
    {
        $resource = fopen(
            'php://memory',
            'r',
        );

        self::assertIsResource(
            $resource,
        );

        $payloads = [
            [],

            [
                'message' => null,
            ],

            [
                'message' => [],
            ],

            [
                'message' => new stdClass(),
            ],

            [
                'message' => "\x00\x01\x02",
            ],

            [
                'message' => "\xB1\x31",
            ],

            [
                'message' => str_repeat(
                    'A',
                    1000000,
                ),
            ],

            [
                'message' => '<script>alert(1)</script>',
            ],

            [
                'message' => "'; DROP TABLE logs; --",
            ],

            [
                'message' => '../../../../../etc/passwd',
            ],

            [
                'requestId' => [],
            ],

            [
                'requestId' => new stdClass(),
            ],

            [
                'requestId' => '<script>',
            ],

            [
                'requestId' => "\x00\x01",
            ],

            [
                'request' => 'invalid',
            ],

            [
                'request' => new stdClass(),
            ],

            [
                'request' => [
                    'method' => [],
                    'uri' => [],
                    'userAgent' => [],
                ],
            ],

            [
                'context' => $resource,
            ],

            [
                'extra' => $resource,
            ],

            [
                'fingerprint' => [],
            ],

            [
                'ip' => [],
            ],

            [
                'uri' => [],
            ],

            [
                'method' => [],
            ],

            [
                'env' => [],
            ],

            [
                'level' => [],
            ],
        ];

        foreach ($payloads as $payload) {
            $entry = $this->factory->create(
                $payload,
            );

            self::assertInstanceOf(
                LogEntry::class,
                $entry,
            );
        }

        fclose(
            $resource,
        );
    }

    /**
     * But : Vérifier que la factory gère un contexte de 10 000 entrées sans crash.
     *
     * Entrée : Payload avec context contenant 10 000 clés 'key-{i}'
     * Résultat attendu : LogEntry créée avec context().count() = 10 000
     */
    public function testItHandlesHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; ++$i) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $entry = $this->factory->create([
            'context' => $context,
        ]);

        self::assertCount(
            10000,
            $entry->context(),
        );
    }

    /**
     * But : Vérifier que la factory gère un extra de 10 000 entrées sans crash.
     *
     * Entrée : Payload avec extra contenant 10 000 clés 'key-{i}'
     * Résultat attendu : LogEntry créée avec extra().count() = 10 000
     */
    public function testItHandlesHugeExtra(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; ++$i) {
            $extra['key-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $entry = $this->factory->create([
            'extra' => $extra,
        ]);

        self::assertCount(
            10000,
            $entry->extra(),
        );
    }

    /**
     * But : Vérifier que la factory normalise correctement une requête imbriquée avec des valeurs énormes.
     *
     * Entrée : method = str_repeat('POST', 1000), uri = str_repeat('/orders', 1000), userAgent = str_repeat('Mozilla/5.0 ', 10000)
     * Résultat attendu : méthode valide, userAgent tronqué ≤ 500, hasIngestionWarnings() = true
     */
    public function testItHandlesHugeNestedRequestPayload(): void
    {
        $entry = $this->factory->create([
            'requestId' => 'req_nested_test',

            'request' => [
                'method' => str_repeat(
                    'POST',
                    1000,
                ),

                'uri' => str_repeat(
                    '/orders',
                    1000,
                ),

                'userAgent' => str_repeat(
                    'Mozilla/5.0 ',
                    10000,
                ),
            ],
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );

        self::assertContains(
            $entry->request()->method(),
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

        self::assertLessThanOrEqual(
            500,
            mb_strlen(
                $entry
                    ->request()
                    ->userAgent(),
            ),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );
    }

    /**
     * But : Vérifier que le warning MESSAGE_TRUNCATED est bien enregistré lors d'une troncature.
     *
     * Entrée : message de 60 000 caractères (str_repeat('ERROR ', 10000))
     * Résultat attendu : message tronqué ≤ 1 000, warning MESSAGE_TRUNCATED présent
     */
    public function testItTracksMessageTruncationWarning(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'ERROR ',
                10000,
            ),
        ]);

        self::assertLessThanOrEqual(
            1000,
            mb_strlen(
                $entry->message(),
            ),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );

        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            IngestionWarningType::MESSAGE_TRUNCATED->value,
            $types,
        );
    }

    /**
     * But : Vérifier que le warning USER_AGENT_TRUNCATED est bien enregistré lors d'une troncature.
     *
     * Entrée : userAgent = str_repeat('Mozilla/5.0 ', 10000) dans request
     * Résultat attendu : userAgent tronqué ≤ 500, warning USER_AGENT_TRUNCATED présent
     */
    public function testItTracksUserAgentTruncationWarning(): void
    {
        $entry = $this->factory->create([
            'request' => [
                'userAgent' => str_repeat(
                    'Mozilla/5.0 ',
                    10000,
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

        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            IngestionWarningType::USER_AGENT_TRUNCATED->value,
            $types,
        );
    }

    /**
     * But : Vérifier que le warning INVALID_IP est bien enregistré lors d'une IP invalide.
     *
     * Entrée : ip = '999.999.999.999'
     * Résultat attendu : ip = '127.0.0.1' (fallback), warning INVALID_IP présent
     */
    public function testItTracksInvalidIpWarning(): void
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

        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            IngestionWarningType::INVALID_IP->value,
            $types,
        );
    }

    /**
     * But : Vérifier que le warning INVALID_REQUEST_ID est bien enregistré pour un requestId invalide.
     *
     * Entrée : requestId = [] (tableau)
     * Résultat attendu : requestId non vide régénéré, warning INVALID_REQUEST_ID présent
     */
    public function testItTracksInvalidRequestIdWarning(): void
    {
        $entry = $this->factory->create([
            'requestId' => [],
        ]);

        self::assertNotEmpty(
            $entry
                ->requestId()
                ->value(),
        );

        $types = array_map(
            static fn ($warning): string => $warning
                ->type()
                ->value,
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            IngestionWarningType::INVALID_REQUEST_ID->value,
            $types,
        );
    }

    /**
     * IMPORTANT :
     * ------------
     * Les tags ont été supprimés du domaine.
     *
     * Ce test vérifie qu'un payload hostile
     * contenant encore "tags" ne provoque
     * aucun crash.
     */
    public function testItIgnoresLegacyTagsPayload(): void
    {
        $tags = [];

        for ($i = 0; $i < 1000; ++$i) {
            $tags['tag-' . $i] = str_repeat(
                'C',
                100,
            );
        }

        $entry = $this->factory->create([
            'tags' => $tags,
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = [
            'message' => str_repeat(
                'ERROR ',
                100000,
            ),

            'requestId' => str_repeat(
                'REQ_',
                10000,
            ),

            'request' => [
                'userAgent' => str_repeat(
                    'Mozilla/5.0 ',
                    100000,
                ),
            ],

            'context' => [
                'huge' => str_repeat(
                    'A',
                    1000000,
                ),
            ],

            'extra' => [
                'huge' => str_repeat(
                    'B',
                    1000000,
                ),
            ],
        ];

        $entry = $this->factory->create(
            $payload,
        );

        self::assertLessThanOrEqual(
            1000,
            mb_strlen(
                $entry->message(),
            ),
        );

        self::assertLessThanOrEqual(
            500,
            mb_strlen(
                $entry
                    ->request()
                    ->userAgent(),
            ),
        );

        self::assertNotEmpty(
            $entry
                ->requestId()
                ->value(),
        );

        self::assertTrue(
            $entry->hasIngestionWarnings(),
        );
    }

    public function testItHandlesInvalidUtf8Payloads(): void
    {
        $payload = hex2bin(
            'b131',
        );

        self::assertNotFalse(
            $payload,
        );

        $entry = $this->factory->create([
            'message' => $payload,

            'requestId' => $payload,

            'request' => [
                'userAgent' => $payload,
            ],

            'context' => [
                'binary' => $payload,
            ],
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesBinaryPayloads(): void
    {
        $entry = $this->factory->create([
            'message' => "\x00\x01\x02",

            'requestId' => "\x00\x01\x02",

            'request' => [
                'userAgent' => "\x00\x01\x02",
            ],

            'context' => [
                'binary' => "\x00\x01\x02",
            ],

            'extra' => [
                'binary' => "\x00\x01\x02",
            ],
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesRepeatedFactoryCalls(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            $entry = $this->factory->create([
                'message' => 'Payment failed',

                'requestId' => 'req_' . $i,

                'context' => [
                    'iteration' => $i,
                ],
            ]);

            self::assertInstanceOf(
                LogEntry::class,
                $entry,
            );
        }
    }

    public function testItProducesStableWarningsStructure(): void
    {
        $entry = $this->factory->create([
            'message' => str_repeat(
                'A',
                10000,
            ),

            'ip' => '999.999.999.999',
        ]);

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
}