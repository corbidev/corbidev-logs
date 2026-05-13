<?php

declare(strict_types=1);

namespace App\Tests\Crash\Persistence\Infrastructure\Entity;

use App\Persistence\Infrastructure\Entity\LogRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests critiques de LogRecord.
 *
 * OBJECTIFS :
 * -----------
 * - garantir absence de crash
 * - garantir robustesse mémoire
 * - garantir stabilité setters/getters
 * - tester payloads hostiles
 * - tester gros payloads
 * - tester sérialisation passive
 *
 * IMPORTANT :
 * ------------
 * Cette entity Doctrine doit rester :
 * - passive
 * - stable
 * - prévisible
 * - sans logique métier
 *
 * GARANTIES :
 * ------------
 * - aucun throw inattendu
 * - aucun traitement implicite
 * - aucune normalisation cachée
 * - stabilité mémoire
 */
#[CoversClass(LogRecord::class)]
final class LogRecordCrashTest extends TestCase
{
    /**
     * But : Vérifier que LogRecord accepte un message de 600 000 caractères sans crash.
     *
     * Entrée : str_repeat('ERROR ', 100000)
     * Résultat attendu : getMessage() retourne la valeur complète sans troncature
     */
    public function testItHandlesHugeMessage(): void
    {
        $record = new LogRecord();

        $message = str_repeat(
            'ERROR ',
            100000,
        );

        $record->setMessage(
            $message,
        );

        self::assertSame(
            $message,
            $record->getMessage(),
        );
    }

    /**
     * But : Vérifier que LogRecord stocke un contexte JSON avec 10 000 clés sans crash.
     *
     * Entrée : Tableau de 10 000 entrées de 1 000 caractères chacune
     * Résultat attendu : getContextJson() retourne 10 000 éléments
     */
    public function testItHandlesHugeContextJson(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; ++$i) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $record = new LogRecord();

        $record->setContextJson(
            $context,
        );

        self::assertCount(
            10000,
            $record->getContextJson(),
        );
    }

    /**
     * But : Vérifier que LogRecord stocke un extra JSON avec 10 000 clés sans crash.
     *
     * Entrée : Tableau de 10 000 entrées de 1 000 caractères chacune
     * Résultat attendu : getExtraJson() retourne 10 000 éléments
     */
    public function testItHandlesHugeExtraJson(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; ++$i) {
            $extra['key-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $record = new LogRecord();

        $record->setExtraJson(
            $extra,
        );

        self::assertCount(
            10000,
            $record->getExtraJson(),
        );
    }

    /**
     * But : Vérifier que LogRecord stocke 5 000 warnings d'ingestion sans crash.
     *
     * Entrée : Tableau de 5 000 warnings
     * Résultat attendu : getIngestionWarningsJson() retourne 5 000 éléments
     */
    public function testItHandlesHugeIngestionWarningsJson(): void
    {
        $warnings = [];

        for ($i = 0; $i < 5000; ++$i) {
            $warnings[] = [
                'type' => 'message_truncated',
                'field' => 'message',
                'message' => str_repeat(
                    'warning ',
                    50,
                ),
            ];
        }

        $record = new LogRecord();

        $record->setIngestionWarningsJson(
            $warnings,
        );

        self::assertCount(
            5000,
            $record->getIngestionWarningsJson(),
        );
    }

    /**
     * But : Vérifier que LogRecord accepte les octets binaires dans message, userAgent et context.
     *
     * Entrée : "\x00\x01\x02" pour plusieurs champs
     * Résultat attendu : getMessage() retourne la valeur binaire telle quelle
     */
    public function testItHandlesBinaryPayloads(): void
    {
        $record = new LogRecord();

        $record->setMessage(
            "\x00\x01\x02",
        );

        $record->setUserAgent(
            "\x00\x01\x02",
        );

        $record->setContextJson([
            'binary' => "\x00\x01\x02",
        ]);

        self::assertSame(
            "\x00\x01\x02",
            $record->getMessage(),
        );
    }

    /**
     * But : Vérifier que LogRecord accepte un payload UTF-8 invalide sans crash.
     *
     * Entrée : hex2bin('b131') pour plusieurs champs
     * Résultat attendu : getMessage() retourne la valeur telle quelle
     */
    public function testItHandlesInvalidUtf8Payloads(): void
    {
        $payload = hex2bin(
            'b131',
        );

        self::assertNotFalse(
            $payload,
        );

        $record = new LogRecord();

        $record->setMessage(
            $payload,
        );

        $record->setUserAgent(
            $payload,
        );

        $record->setContextJson([
            'invalid' => $payload,
        ]);

        self::assertSame(
            $payload,
            $record->getMessage(),
        );
    }

    /**
     * But : Vérifier que LogRecord stocke sans modification les payloads XSS, traversal et injection SQL.
     *
     * Entrée : '<script>alert(1)</script>', '../../../../../etc/passwd', "'; DROP TABLE logs; --"
     * Résultat attendu : Les valeurs sont stockées telles quelles (pas de sanitization dans l'entity)
     */
    public function testItHandlesHostilePayloads(): void
    {
        $record = new LogRecord();

        $record->setMessage(
            '<script>alert(1)</script>',
        );

        $record->setUri(
            '../../../../../etc/passwd',
        );

        $record->setUserAgent(
            "'; DROP TABLE logs; --",
        );

        self::assertSame(
            '<script>alert(1)</script>',
            $record->getMessage(),
        );

        self::assertSame(
            '../../../../../etc/passwd',
            $record->getUri(),
        );

        self::assertSame(
            "'; DROP TABLE logs; --",
            $record->getUserAgent(),
        );
    }

    /**
     * But : Vérifier que setRequestId() accepte un requestId de grande taille sans crash.
     *
     * Entrée : str_repeat('req_', 10000)
     * Résultat attendu : getRequestId() retourne la valeur complète
     */
    public function testItHandlesHugeRequestId(): void
    {
        $record = new LogRecord();

        $requestId = str_repeat(
            'req_',
            10000,
        );

        $record->setRequestId(
            $requestId,
        );

        self::assertSame(
            $requestId,
            $record->getRequestId(),
        );
    }

    /**
     * But : Vérifier que setUserAgent() accepte un user-agent de très grande taille sans crash.
     *
     * Entrée : str_repeat('Mozilla/5.0 ', 100000)
     * Résultat attendu : getUserAgent() retourne la valeur complète
     */
    public function testItHandlesHugeUserAgent(): void
    {
        $record = new LogRecord();

        $userAgent = str_repeat(
            'Mozilla/5.0 ',
            100000,
        );

        $record->setUserAgent(
            $userAgent,
        );

        self::assertSame(
            $userAgent,
            $record->getUserAgent(),
        );
    }

    /**
     * But : Vérifier que setUri() accepte une URI de très grande taille sans crash.
     *
     * Entrée : '/' . str_repeat('segment/', 10000)
     * Résultat attendu : getUri() retourne la valeur complète
     */
    public function testItHandlesHugeUri(): void
    {
        $record = new LogRecord();

        $uri = '/'
            . str_repeat(
                'segment/',
                10000,
            );

        $record->setUri(
            $uri,
        );

        self::assertSame(
            $uri,
            $record->getUri(),
        );
    }

    /**
     * But : Vérifier que setFingerprint() accepte un fingerprint de grande taille sans crash.
     *
     * Entrée : str_repeat('abcdef1234567890', 1000)
     * Résultat attendu : getFingerprint() retourne la valeur complète
     */
    public function testItHandlesHugeFingerprint(): void
    {
        $record = new LogRecord();

        $fingerprint = str_repeat(
            'abcdef1234567890',
            1000,
        );

        $record->setFingerprint(
            $fingerprint,
        );

        self::assertSame(
            $fingerprint,
            $record->getFingerprint(),
        );
    }

    /**
     * But : Vérifier que 5 000 hydrations successives de LogRecord n'entraînent pas de crash mémoire.
     *
     * Entrée : 5 000 itérations avec hydration complète
     * Résultat attendu : requestId et ip corrects à chaque itération, aucun crash
     */
    public function testItHandlesRepeatedHydrationWithoutCrash(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            $record = new LogRecord();

            $record->setExternalId(
                'id-' . $i,
            );

            $record->setProjectId(
                (string) $i,
            );

            $record->setFingerprint(
                'abcdef1234567890',
            );

            $record->setRequestId(
                'req_' . $i,
            );

            $record->setLevel(
                'error',
            );

            $record->setHttpStatus(
                500,
            );

            $record->setDomain(
                'billing',
            );

            $record->setUri(
                '/orders',
            );

            $record->setMethod(
                'POST',
            );

            $record->setUserAgent(
                'Mozilla/5.0',
            );

            $record->setEnv(
                'prod',
            );

            $record->setClient(
                'checkout-app',
            );

            $record->setMessage(
                'Payment failed',
            );

            $record->setContextJson([
                'iteration' => $i,
            ]);

            $record->setExtraJson([
                'memory' => 123,
            ]);

            $record->setIngestionWarningsJson([
                [
                    'type' => 'normalized',
                    'field' => 'message',
                ],
            ]);

            $record->setCreatedAt(
                new DateTimeImmutable(),
            );

            $record->setClientDate(
                new DateTimeImmutable(),
            );

            $record->setIp(
                '127.0.0.1',
            );

            self::assertSame(
                'req_' . $i,
                $record->getRequestId(),
            );

            self::assertSame(
                '127.0.0.1',
                $record->getIp(),
            );
        }
    }

    /**
     * But : Vérifier que 10 000 appels successifs aux getters ne causent pas de crash.
     *
     * Entrée : LogRecord hydraté, 10 000 itérations de lecture
     * Résultat attendu : Les valeurs restent stables, aucun crash
     */
    public function testItHandlesRepeatedGetterCallsWithoutCrash(): void
    {
        $record = $this->createRecord();

        for ($i = 0; $i < 10000; ++$i) {
            self::assertSame(
                'external-id',
                $record->getExternalId(),
            );

            self::assertSame(
                'abcdef1234567890',
                $record->getFingerprint(),
            );

            self::assertSame(
                'req_test',
                $record->getRequestId(),
            );

            self::assertSame(
                'Payment failed',
                $record->getMessage(),
            );
        }
    }

    private function createRecord(): LogRecord
    {
        $record = new LogRecord();

        $record->setExternalId(
            'external-id',
        );

        $record->setProjectId(
            '1',
        );

        $record->setFingerprint(
            'abcdef1234567890',
        );

        $record->setRequestId(
            'req_test',
        );

        $record->setLevel(
            'error',
        );

        $record->setHttpStatus(
            500,
        );

        $record->setDomain(
            'billing',
        );

        $record->setUri(
            '/orders',
        );

        $record->setMethod(
            'POST',
        );

        $record->setUserAgent(
            'Mozilla/5.0',
        );

        $record->setEnv(
            'prod',
        );

        $record->setClient(
            'checkout-app',
        );

        $record->setMessage(
            'Payment failed',
        );

        $record->setContextJson([]);

        $record->setExtraJson([]);

        $record->setIngestionWarningsJson([]);

        $record->setCreatedAt(
            new DateTimeImmutable(),
        );

        $record->setIp(
            '127.0.0.1',
        );

        return $record;
    }
}