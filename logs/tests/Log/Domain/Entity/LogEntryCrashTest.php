<?php

declare(strict_types=1);

namespace App\Tests\Crash\Log\Domain\Entity;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests critiques de LogEntry.
 *
 * OBJECTIFS :
 * -----------
 * - garantir absence de crash
 * - garantir robustesse mémoire
 * - garantir stabilité domaine
 * - garantir stabilité serialization
 * - tester payloads hostiles
 * - tester payloads binaires
 * - tester structures volumineuses
 *
 * IMPORTANT :
 * ------------
 * Ces crash tests doivent :
 * - rester déterministes
 * - rester bornés
 * - ne jamais dépendre de Symfony runtime
 * - ne jamais dépendre de Doctrine
 * - ne jamais dépendre du filesystem
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - gros payloads
 * - binary payloads
 * - invalid UTF-8
 * - injections
 * - structures profondes
 * - gros tableaux
 * - sérialisation stable
 * - robustesse mémoire
 * - stabilité ingestionWarnings
 */
#[CoversClass(LogEntry::class)]
final class LogEntryCrashTest extends TestCase
{
    /**
     * But : Vérifier que LogEntry accepte un contexte de 10 000 entrées sans crash.
     *
     * Entrée : Tableau context avec 10 000 clés 'key_{i}'
     * Résultat attendu : Instance LogEntry créée, contexte de 10 000 entrées
     */
    public function testItHandlesHugeContextWithoutCrash(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; ++$i) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $entry = $this->createEntry(
            context: $context,
        );

        self::assertCount(
            10000,
            $entry->context(),
        );
    }

    /**
     * But : Vérifier que LogEntry accepte un extra de 10 000 entrées sans crash.
     *
     * Entrée : Tableau extra avec 10 000 clés 'key_{i}'
     * Résultat attendu : Instance LogEntry créée, extra de 10 000 entrées
     */
    public function testItHandlesHugeExtraWithoutCrash(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; ++$i) {
            $extra['extra-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $entry = $this->createEntry(
            extra: $extra,
        );

        self::assertCount(
            10000,
            $entry->extra(),
        );
    }

    /**
     * But : Vérifier que LogEntry accepte un contexte imbriqué sur 5 niveaux sans crash.
     *
     * Entrée : Contexte imbriqué ['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]]
     * Résultat attendu : Instance LogEntry créée, imbrication conservée
     */
    public function testItHandlesDeepNestedPayloadWithoutCrash(): void
    {
        $payload = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => 'deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $entry = $this->createEntry(
            context: $payload,
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte une string de 5 Mo dans le contexte sans crash.
     *
     * Entrée : context = ['data' => str_repeat('X', 5 * 1024 * 1024)]
     * Résultat attendu : Instance LogEntry créée sans exception
     */
    public function testItHandlesHugeStringPayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'X',
            5_000_000,
        );

        $entry = $this->createEntry(
            context: [
                'huge' => $payload,
            ],
        );

        self::assertSame(
            $payload,
            $entry->context()['huge'],
        );
    }

    /**
     * But : Vérifier que LogEntry accepte des octets binaires dans context et extra sans crash.
     *
     * Entrée : context = ["\x00\x01\x02"], extra = ["\x00\x01\x02"]
     * Résultat attendu : Instance LogEntry créée sans exception
     */
    public function testItHandlesBinaryPayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'binary' => "\x00\x01\x02",
            ],
            extra: [
                'binary' => "\x00\x01\x02",
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte de l'UTF-8 invalide dans context et extra sans crash.
     *
     * Entrée : context = [hex2bin('b131')], extra = [hex2bin('b131')]
     * Résultat attendu : Instance LogEntry créée sans exception
     */
    public function testItHandlesInvalidUtf8Payloads(): void
    {
        $payload = hex2bin(
            'b131',
        );

        self::assertNotFalse(
            $payload,
        );

        $entry = $this->createEntry(
            context: [
                'utf8' => $payload,
            ],
            extra: [
                'utf8' => $payload,
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte des octets nuls dans le contexte sans crash.
     *
     * Entrée : context = ["abc\0def" => "value\0null"]
     * Résultat attendu : Instance LogEntry créée sans exception
     */
    public function testItHandlesNullBytesWithoutCrash(): void
    {
        $payload = "abc\0def";

        $entry = $this->createEntry(
            context: [
                'null-byte' => $payload,
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte des payloads hostiles (XSS, SQL, path traversal, shell) sans crash.
     *
     * Entrée : context avec injections XSS, SQL, path traversal et shell
     * Résultat attendu : Instance LogEntry créée sans exception
     */
    public function testItHandlesHostilePayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'xss' => '<script>alert(1)</script>',
                'sql' => "'; DROP TABLE logs; --",
                'path' => '../../../../../etc/passwd',
                'shell' => '$(rm -rf /)',
                'php' => '<?php phpinfo();',
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte du JSON encodé dans le contexte sans crash.
     *
     * Entrée : context = ['data' => '{"key":"value"}']
     * Résultat attendu : Instance LogEntry créée, contexte conservé
     */
    public function testItHandlesJsonPayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'json' => json_encode([
                    'a' => 1,
                    'b' => 2,
                ]),
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que LogEntry accepte des emoji et du texte multilingue sans crash.
     *
     * Entrée : context = ['text' => 'Hello 🌍 Héllo Мир 日本語']
     * Résultat attendu : Instance LogEntry créée, contexte conservé
     */
    public function testItHandlesUnicodePayloads(): void
    {
        $entry = $this->createEntry(
            context: [
                'unicode' => '🔥 éèà 中文 русский',
            ],
        );

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    /**
     * But : Vérifier que toArray() est stable avec un contexte de 5 000 entrées.
     *
     * Entrée : context avec 5 000 clés 'key_{i}'
     * Résultat attendu : toArray() retourne un tableau sans crash
     */
    public function testItHandlesLargeSerializationWithoutCrash(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key-' . $i] = [
                'memory' => str_repeat(
                    'A',
                    500,
                ),
            ];
        }

        $entry = $this->createEntry(
            context: $context,
        );

        $serialized = $entry->toArray();

        self::assertCount(
            5000,
            $serialized['context'],
        );
    }

    /**
     * But : Vérifier que la création de 1 000 instances de LogEntry ne provoque pas de crash.
     *
     * Entrée : 1 000 instanciations avec message 'entry-{i}'
     * Résultat attendu : Chaque instance a un id non vide, aucune exception
     */
    public function testItHandlesManyInstancesWithoutCrash(): void
    {
        $entries = [];

        for ($i = 0; $i < 1000; ++$i) {
            $entries[] = $this->createEntry(
                context: [
                    'iteration' => $i,
                ],
            );
        }

        self::assertCount(
            1000,
            $entries,
        );
    }

    /**
     * But : Vérifier que toArray() peut être appelé 1 000 fois de suite sans crash.
     *
     * Entrée : 1 instance, 1 000 appels à toArray()
     * Résultat attendu : Résultat identique à chaque appel, aucune exception
     */
    public function testItHandlesRepeatedSerializationWithoutCrash(): void
    {
        $entry = $this->createEntry();

        for ($i = 0; $i < 1000; ++$i) {
            self::assertIsArray(
                $entry->toArray(),
            );
        }
    }

    /**
     * But : Vérifier que LogEntry accepte 5 000 IngestionWarnings sans crash.
     *
     * Entrée : Tableau de 5 000 IngestionWarning
     * Résultat attendu : hasIngestionWarnings() = true, count = 5 000
     */
    public function testItHandlesHugeIngestionWarningsWithoutCrash(): void
    {
        $warnings = [];

        for ($i = 0; $i < 5000; ++$i) {
            $warnings[] = new IngestionWarning(
                field: 'field-' . $i,
                type: IngestionWarningType::MESSAGE_TRUNCATED,
                original: str_repeat(
                    'payload',
                    50,
                ),
                fallback: 'truncated',
            );
        }

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        self::assertCount(
            5000,
            $entry->ingestionWarnings(),
        );
    }

    /**
     * But : Vérifier que toArray() avec 1 000 warnings est stable.
     *
     * Entrée : 1 000 IngestionWarning
     * Résultat attendu : toArray() contient la clé 'ingestionWarnings' avec 1 000 éléments
     */
    public function testItSerializesHugeWarningsWithoutCrash(): void
    {
        $warnings = [];

        for ($i = 0; $i < 1000; ++$i) {
            $warnings[] = new IngestionWarning(
                field: 'ip',
                type: IngestionWarningType::INVALID_IP,
                original: '999.999.999.999',
                fallback: '127.0.0.1',
            );
        }

        $entry = $this->createEntry(
            ingestionWarnings: $warnings,
        );

        $data = $entry->toArray();

        self::assertCount(
            1000,
            $data['ingestionWarnings'],
        );
    }

    /**
     * But : Vérifier que LogEntry rejette des warnings invalides (non-IngestionWarning).
     *
     * Entrée : Tableau de warnings contenant une stdClass
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsInvalidWarnings(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            ingestionWarnings: [
                'invalid-warning',
            ],
        );
    }

    /**
     * But : Vérifier que LogEntry rejette un message dépassant la longueur maximale.
     *
     * Entrée : Message de 1 001 caractères
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsHugeMessage(): void
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
     * But : Vérifier que LogEntry rejette un domaine dépassant la longueur maximale.
     *
     * Entrée : Domaine de 101 caractères
     * Résultat attendu : InvalidLogEntryException est levée
     */
    public function testItRejectsHugeDomain(): void
    {
        $this->expectException(
            InvalidLogEntryException::class,
        );

        $this->createEntry(
            domain: str_repeat(
                'billing',
                100,
            ),
        );
    }

    /**
     * @param list<IngestionWarning|mixed> $ingestionWarnings
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createEntry(
        string $message = 'Payment failed',
        string $domain = 'billing',
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: LogLevel::ERROR,
            domain: $domain,
            environment: Environment::Production,
            httpStatus: new HttpStatus(500),
            client: new Client('checkout-app'),
            request: new Request(
                method: 'POST',
                uri: new Uri('/orders'),
                userAgent: 'Mozilla/5.0',
            ),
            ipAddress: new IpAddress('127.0.0.1'),
            fingerprint: new Fingerprint(
                'abcdef1234567890',
            ),
            requestId: new RequestId(
                'req_crash_test',
            ),
            ingestionWarnings: $ingestionWarnings,
            context: $context,
            extra: $extra,
        );
    }
}