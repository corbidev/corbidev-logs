<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Infrastructure\Entity;

use App\Persistence\Infrastructure\Entity\LogRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires de LogRecord.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité mapping
 * - robustesse getters/setters
 * - cohérence persistence
 * - absence de logique métier
 * - stabilité long terme
 *
 * IMPORTANT :
 * ------------
 * Cette entity Doctrine doit rester :
 * - anémique
 * - prédictible
 * - stable
 * - purement technique
 * - sans comportement métier
 *
 * GARANTIES :
 * ------------
 * - aucun traitement implicite
 * - aucun effet de bord
 * - aucun mapping magique
 * - aucune mutation cachée
 */
#[CoversClass(LogRecord::class)]
final class LogRecordTest extends TestCase
{
    /**
     * But : Vérifier que setExternalId()/getExternalId() stocke et retourne la valeur.
     *
     * Entrée : UUID '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21'
     * Résultat attendu : getExternalId() retourne la valeur définie
     */
    public function testItStoresExternalId(): void
    {
        $record = new LogRecord();

        $record->setExternalId(
            '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21',
        );

        self::assertSame(
            '018f0d9b-fe16-7cb2-b40c-3c4f1e8b6f21',
            $record->getExternalId(),
        );
    }

    /**
     * But : Vérifier que setDomainId()/getDomainId() stocke et retourne la valeur.
     *
     * Entrée : '42'
     * Résultat attendu : getDomainId() = '42'
     */
    public function testItStoresDomainId(): void
    {
        $record = new LogRecord();

        $record->setDomainId(
            '42',
        );

        self::assertSame(
            '42',
            $record->getDomainId(),
        );
    }

    /**
     * But : Vérifier que setFingerprint()/getFingerprint() stocke et retourne la valeur.
     *
     * Entrée : 'abcdef1234567890'
     * Résultat attendu : getFingerprint() = 'abcdef1234567890'
     */
    public function testItStoresFingerprint(): void
    {
        $record = new LogRecord();

        $record->setFingerprint(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $record->getFingerprint(),
        );
    }

    /**
     * But : Vérifier que setRequestId()/getRequestId() stocke et retourne la valeur.
     *
     * Entrée : 'req_checkout_123'
     * Résultat attendu : getRequestId() = 'req_checkout_123'
     */
    public function testItStoresRequestId(): void
    {
        $record = new LogRecord();

        $record->setRequestId(
            'req_checkout_123',
        );

        self::assertSame(
            'req_checkout_123',
            $record->getRequestId(),
        );
    }

    /**
     * But : Vérifier que setLevel()/getLevel() stocke et retourne la valeur.
     *
     * Entrée : 'error'
     * Résultat attendu : getLevel() = 'error'
     */
    public function testItStoresLevel(): void
    {
        $record = new LogRecord();

        $record->setLevel(
            'error',
        );

        self::assertSame(
            'error',
            $record->getLevel(),
        );
    }

    /**
     * But : Vérifier que setHttpStatus()/getHttpStatus() stocke et retourne la valeur entière.
     *
     * Entrée : 500
     * Résultat attendu : getHttpStatus() = 500
     */
    public function testItStoresHttpStatus(): void
    {
        $record = new LogRecord();

        $record->setHttpStatus(
            500,
        );

        self::assertSame(
            500,
            $record->getHttpStatus(),
        );
    }

    /**
     * But : Vérifier que setDomain()/getDomain() stocke et retourne la valeur.
     *
     * Entrée : 'billing'
     * Résultat attendu : getDomain() = 'billing'
     */
    public function testItStoresDomain(): void
    {
        $record = new LogRecord();

        $record->setDomain(
            'billing',
        );

        self::assertSame(
            'billing',
            $record->getDomain(),
        );
    }

    /**
     * But : Vérifier que setUri()/getUri() stocke et retourne la valeur.
     *
     * Entrée : '/orders'
     * Résultat attendu : getUri() = '/orders'
     */
    public function testItStoresUri(): void
    {
        $record = new LogRecord();

        $record->setUri(
            '/orders',
        );

        self::assertSame(
            '/orders',
            $record->getUri(),
        );
    }

    /**
     * But : Vérifier que setMethod()/getMethod() stocke et retourne la valeur.
     *
     * Entrée : 'POST'
     * Résultat attendu : getMethod() = 'POST'
     */
    public function testItStoresMethod(): void
    {
        $record = new LogRecord();

        $record->setMethod(
            'POST',
        );

        self::assertSame(
            'POST',
            $record->getMethod(),
        );
    }

    /**
     * But : Vérifier que setMethod(null) est accepté et getMethod() retourne null.
     *
     * Entrée : null
     * Résultat attendu : getMethod() = null
     */
    public function testItStoresNullableMethod(): void
    {
        $record = new LogRecord();

        $record->setMethod(
            null,
        );

        self::assertNull(
            $record->getMethod(),
        );
    }

    /**
     * But : Vérifier que setUserAgent()/getUserAgent() stocke et retourne la valeur.
     *
     * Entrée : 'Mozilla/5.0'
     * Résultat attendu : getUserAgent() = 'Mozilla/5.0'
     */
    public function testItStoresUserAgent(): void
    {
        $record = new LogRecord();

        $record->setUserAgent(
            'Mozilla/5.0',
        );

        self::assertSame(
            'Mozilla/5.0',
            $record->getUserAgent(),
        );
    }

    /**
     * But : Vérifier que setUserAgent(null) est accepté et getUserAgent() retourne null.
     *
     * Entrée : null
     * Résultat attendu : getUserAgent() = null
     */
    public function testItStoresNullableUserAgent(): void
    {
        $record = new LogRecord();

        $record->setUserAgent(
            null,
        );

        self::assertNull(
            $record->getUserAgent(),
        );
    }

    /**
     * But : Vérifier que setEnv()/getEnv() stocke et retourne la valeur.
     *
     * Entrée : 'prod'
     * Résultat attendu : getEnv() = 'prod'
     */
    public function testItStoresEnvironment(): void
    {
        $record = new LogRecord();

        $record->setEnv(
            'prod',
        );

        self::assertSame(
            'prod',
            $record->getEnv(),
        );
    }

    /**
     * But : Vérifier que setClient()/getClient() stocke et retourne la valeur.
     *
     * Entrée : 'checkout-app'
     * Résultat attendu : getClient() = 'checkout-app'
     */
    public function testItStoresClient(): void
    {
        $record = new LogRecord();

        $record->setClient(
            'checkout-app',
        );

        self::assertSame(
            'checkout-app',
            $record->getClient(),
        );
    }

    /**
     * But : Vérifier que setMessage()/getMessage() stocke et retourne la valeur.
     *
     * Entrée : 'Payment failed'
     * Résultat attendu : getMessage() = 'Payment failed'
     */
    public function testItStoresMessage(): void
    {
        $record = new LogRecord();

        $record->setMessage(
            'Payment failed',
        );

        self::assertSame(
            'Payment failed',
            $record->getMessage(),
        );
    }

    /**
     * But : Vérifier que setContextJson()/getContextJson() stocke et retourne le tableau.
     *
     * Entrée : ['userId' => 42]
     * Résultat attendu : getContextJson() retourne le même tableau
     */
    public function testItStoresContextJson(): void
    {
        $context = [
            'userId' => 42,
        ];

        $record = new LogRecord();

        $record->setContextJson(
            $context,
        );

        self::assertSame(
            $context,
            $record->getContextJson(),
        );
    }

    /**
     * But : Vérifier que setContextJson([]) est accepté et getContextJson() retourne [].
     *
     * Entrée : []
     * Résultat attendu : getContextJson() = []
     */
    public function testItStoresEmptyContextJson(): void
    {
        $record = new LogRecord();

        $record->setContextJson(
            [],
        );

        self::assertSame(
            [],
            $record->getContextJson(),
        );
    }

    /**
     * But : Vérifier que setExtraJson()/getExtraJson() stocke et retourne le tableau.
     *
     * Entrée : ['memory' => 123]
     * Résultat attendu : getExtraJson() retourne le même tableau
     */
    public function testItStoresExtraJson(): void
    {
        $extra = [
            'memory' => 123,
        ];

        $record = new LogRecord();

        $record->setExtraJson(
            $extra,
        );

        self::assertSame(
            $extra,
            $record->getExtraJson(),
        );
    }

    /**
     * But : Vérifier que setExtraJson([]) est accepté et getExtraJson() retourne [].
     *
     * Entrée : []
     * Résultat attendu : getExtraJson() = []
     */
    public function testItStoresEmptyExtraJson(): void
    {
        $record = new LogRecord();

        $record->setExtraJson(
            [],
        );

        self::assertSame(
            [],
            $record->getExtraJson(),
        );
    }

    /**
     * But : Vérifier que setIngestionWarningsJson()/getIngestionWarningsJson() stocke et retourne le tableau.
     *
     * Entrée : Tableau avec un warning de type 'message_truncated'
     * Résultat attendu : getIngestionWarningsJson() retourne le même tableau
     */
    public function testItStoresIngestionWarningsJson(): void
    {
        $warnings = [
            [
                'type' => 'message_truncated',
                'field' => 'message',
                'message' => 'Message truncated',
            ],
        ];

        $record = new LogRecord();

        $record->setIngestionWarningsJson(
            $warnings,
        );

        self::assertSame(
            $warnings,
            $record->getIngestionWarningsJson(),
        );
    }

    /**
     * But : Vérifier que setIngestionWarningsJson([]) est accepté et la liste retourne [].
     *
     * Entrée : []
     * Résultat attendu : getIngestionWarningsJson() = []
     */
    public function testItStoresEmptyIngestionWarningsJson(): void
    {
        $record = new LogRecord();

        $record->setIngestionWarningsJson(
            [],
        );

        self::assertSame(
            [],
            $record->getIngestionWarningsJson(),
        );
    }

    /**
     * But : Vérifier que setCreatedAt()/getCreatedAt() stocke et retourne la date.
     *
     * Entrée : new DateTimeImmutable()
     * Résultat attendu : getCreatedAt() retourne la même instance
     */
    public function testItStoresCreatedAt(): void
    {
        $date = new DateTimeImmutable();

        $record = new LogRecord();

        $record->setCreatedAt(
            $date,
        );

        self::assertSame(
            $date,
            $record->getCreatedAt(),
        );
    }

    /**
     * But : Vérifier que setClientDate()/getClientDate() stocke et retourne la date.
     *
     * Entrée : new DateTimeImmutable()
     * Résultat attendu : getClientDate() retourne la même instance
     */
    public function testItStoresClientDate(): void
    {
        $date = new DateTimeImmutable();

        $record = new LogRecord();

        $record->setClientDate(
            $date,
        );

        self::assertSame(
            $date,
            $record->getClientDate(),
        );
    }

    /**
     * But : Vérifier que setClientDate(null) est accepté et getClientDate() retourne null.
     *
     * Entrée : null
     * Résultat attendu : getClientDate() = null
     */
    public function testItStoresNullableClientDate(): void
    {
        $record = new LogRecord();

        $record->setClientDate(
            null,
        );

        self::assertNull(
            $record->getClientDate(),
        );
    }

    /**
     * But : Vérifier que setIp()/getIp() stocke et retourne l'adresse IP.
     *
     * Entrée : '127.0.0.1'
     * Résultat attendu : getIp() = '127.0.0.1'
     */
    public function testItStoresIp(): void
    {
        $record = new LogRecord();

        $record->setIp(
            '127.0.0.1',
        );

        self::assertSame(
            '127.0.0.1',
            $record->getIp(),
        );
    }

    /**
     * But : Vérifier que setIp(null) est accepté et getIp() retourne null.
     *
     * Entrée : null
     * Résultat attendu : getIp() = null
     */
    public function testItStoresNullableIp(): void
    {
        $record = new LogRecord();

        $record->setIp(
            null,
        );

        self::assertNull(
            $record->getIp(),
        );
    }

    /**
     * But : Vérifier que getId() retourne null avant toute persistance Doctrine.
     *
     * Entrée : new LogRecord() sans persistance
     * Résultat attendu : getId() = null
     */
    public function testItStoresNullableIdBeforePersistence(): void
    {
        $record = new LogRecord();

        self::assertNull(
            $record->getId(),
        );
    }

    /**
     * But : Vérifier que les getters restent stables lors de 1000 appels successifs.
     *
     * Entrée : LogRecord hydraté, 1000 itérations
     * Résultat attendu : Les valeurs retournées sont identiques à chaque appel
     */
    public function testItSupportsRepeatedGetterCalls(): void
    {
        $record = $this->createRecord();

        for ($i = 0; $i < 1000; ++$i) {
            self::assertSame(
                'external-id',
                $record->getExternalId(),
            );

            self::assertSame(
                'billing',
                $record->getDomain(),
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

        $record->setDomainId(
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