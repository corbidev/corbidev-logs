<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use App\Log\Domain\Exception\InvalidClientException;
use App\Log\Domain\Exception\InvalidFingerprintException;
use App\Log\Domain\Exception\InvalidHttpStatusException;
use App\Log\Domain\Exception\InvalidIpAddressException;
use App\Log\Domain\Exception\InvalidRequestIdException;
use App\Log\Domain\Exception\InvalidUriException;
use App\Log\Enum\IngestionWarningType;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de LogEntryFactory.
 *
 * OBJECTIFS :
 * -----------
 * - garantir robustesse
 * - garantir stabilité mémoire
 * - garantir absence de crash
 * - garantir cohérence des invariants
 * - garantir stabilité des warnings ingestion
 *
 * IMPORTANT :
 * ------------
 * Cette factory DOIT :
 * - toujours produire des LogEntry valides
 * - préserver les invariants Domain
 * - tracer les corrections ingestion
 *
 * Les crash tests ne doivent JAMAIS :
 * - bypass le Domain
 * - contourner les invariants
 * - produire des états invalides
 */
final class LogEntryFactoryCrashTest extends TestCase
{
    /**
     * But : Vérifier que la création de 10 000 entrées ne provoque aucun crash.
     *
     * Entrée : LogEntryFactory::many(10000)
     * Résultat attendu : count = 10000
     */
    public function testItSurvivesHugeBatchCreation(): void
    {
        $entries = LogEntryFactory::many(
            10000,
        );

        self::assertCount(
            10000,
            $entries,
        );
    }

    /**
     * But : Vérifier que les messages de 5 000 caractères sont tronqués à 1 000.
     *
     * Entrée : message = str_repeat('a', 5000)
     * Résultat attendu : mb_strlen = 1000, warning MESSAGE_TRUNCATED présent
     */
    public function testItTruncatesVeryLongMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: str_repeat(
                'a',
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
     * But : Vérifier qu'un httpStatus négatif (-500) est rejeté.
     *
     * Entrée : httpStatus = -500
     * Résultat attendu : Lève une exception `InvalidHttpStatusException`
     */
    public function testItRejectsInvalidHttpStatus(): void
    {
        $this->expectException(
            InvalidHttpStatusException::class,
        );

        LogEntryFactory::create(
            httpStatus: -500,
        );
    }

    /**
     * But : Vérifier qu'une adresse IP invalide est rejetée.
     *
     * Entrée : ip = 'invalid-ip'
     * Résultat attendu : Lève une exception `InvalidIpAddressException`
     */
    public function testItRejectsInvalidIp(): void
    {
        $this->expectException(
            InvalidIpAddressException::class,
        );

        LogEntryFactory::create(
            ip: 'invalid-ip',
        );
    }

    /**
     * But : Vérifier qu'un fingerprint invalide est rejeté.
     *
     * Entrée : fingerprint = 'INVALID'
     * Résultat attendu : Lève une exception `InvalidFingerprintException`
     */
    public function testItRejectsInvalidFingerprint(): void
    {
        $this->expectException(
            InvalidFingerprintException::class,
        );

        LogEntryFactory::create(
            fingerprint: 'INVALID',
        );
    }

    /**
     * But : Vérifier que le context peut contenir 5 000 clés sans crash.
     *
     * Entrée : context avec 5000 clés
     * Résultat attendu : count(context) = 5000
     */
    public function testItSurvivesHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key_' . $i] = $i;
        }

        $entry = LogEntryFactory::create(
            context: $context,
        );

        self::assertCount(
            5000,
            $entry->getContext(),
        );
    }

    /**
     * But : Vérifier que extra peut contenir 5 000 clés sans crash.
     *
     * Entrée : extra avec 5000 clés
     * Résultat attendu : count(extra) = 5000
     */
    public function testItSurvivesHugeExtra(): void
    {
        $extra = [];

        for ($i = 0; $i < 5000; ++$i) {
            $extra['extra_' . $i] = $i;
        }

        $entry = LogEntryFactory::create(
            extra: $extra,
        );

        self::assertCount(
            5000,
            $entry->getExtra(),
        );
    }

    /**
     * But : Vérifier que les messages avec caractères Unicode sont conservés.
     *
     * Entrée : message = 'Erreur 漢字 🚀 éàç'
     * Résultat attendu : message conservé identique
     */
    public function testItSurvivesUnicodeMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: 'Erreur 漢字 🚀 éàç',
        );

        self::assertSame(
            'Erreur 漢字 🚀 éàç',
            $entry->getMessage(),
        );
    }

    /**
     * But : Vérifier que 2 000 appels consécutifs ne provoquent aucun crash.
     *
     * Entrée : 2000 appels à LogEntryFactory::create()
     * Résultat attendu : Chaque entry->id() est non vide
     */
    public function testItSurvivesMassiveLoop(): void
    {
        for ($i = 0; $i < 2000; ++$i) {
            $entry = LogEntryFactory::create();

            self::assertNotEmpty(
                $entry->getExternalId(),
            );
        }
    }

    /**
     * But : Vérifier que 3 000 fingerprints sont générés au bon format.
     *
     * Entrée : 3000 appels create(message='message-{i}')
     * Résultat attendu : Chaque fingerprint correspond à /^[a-f0-9]{16}$/
     */
    public function testItSurvivesMassiveFingerprintGeneration(): void
    {
        for ($i = 0; $i < 3000; ++$i) {
            $entry = LogEntryFactory::create(
                message: 'message-' . $i,
            );

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{16}$/',
                $entry
                    ->getFingerprint()
                    ->value(),
            );
        }
    }

    /**
     * But : Vérifier qu'une URI trop longue est rejetée.
     *
     * Entrée : uri = '/' + str_repeat('segment/', 300)
     * Résultat attendu : Lève une exception `InvalidUriException`
     */
    public function testItRejectsHugeUri(): void
    {
        $this->expectException(
            InvalidUriException::class,
        );

        LogEntryFactory::create(
            uri: '/'
                . str_repeat(
                    'segment/',
                    300,
                ),
        );
    }

    /**
     * But : Vérifier que les User-Agent trop longs sont tronqués à 500 caractères.
     *
     * Entrée : userAgent = str_repeat('Mozilla/5.0 ', 500)
     * Résultat attendu : mb_strlen(userAgent) = 500, warning USER_AGENT_TRUNCATED présent
     */
    public function testItTruncatesHugeUserAgent(): void
    {
        $entry = LogEntryFactory::create(
            userAgent: str_repeat(
                'Mozilla/5.0 ',
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
     * But : Vérifier qu'un nom de client trop long est rejeté.
     *
     * Entrée : client = str_repeat('phpunit-client-', 100)
     * Résultat attendu : Lève une exception `InvalidClientException`
     */
    public function testItRejectsHugeClientName(): void
    {
        $this->expectException(
            InvalidClientException::class,
        );

        LogEntryFactory::create(
            client: str_repeat(
                'phpunit-client-',
                100,
            ),
        );
    }

    /**
     * But : Vérifier qu'une méthode HTTP invalide (trop longue) est normalisée.
     *
     * Entrée : method = str_repeat('POST', 200)
     * Résultat attendu : request()->method() est une méthode HTTP valide
     */
    public function testItNormalizesHugeMethod(): void
    {
        $entry = LogEntryFactory::create(
            method: str_repeat(
                'POST',
                200,
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
     * But : Vérifier qu'un requestId trop long est rejeté.
     *
     * Entrée : requestId = str_repeat('req_', 50)
     * Résultat attendu : Lève une exception `InvalidRequestIdException`
     */
    public function testItRejectsHugeRequestId(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        LogEntryFactory::create(
            requestId: str_repeat(
                'req_',
                50,
            ),
        );
    }

    /**
     * But : Vérifier que many(-500) retourne un tableau vide sans crash.
     *
     * Entrée : LogEntryFactory::many(-500)
     * Résultat attendu : []
     */
    public function testItSurvivesNegativeMany(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(-500),
        );
    }

    /**
     * But : Vérifier que many(0) retourne un tableau vide.
     *
     * Entrée : LogEntryFactory::many(0)
     * Résultat attendu : []
     */
    public function testItSurvivesZeroMany(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(0),
        );
    }

    /**
     * But : Vérifier que les warnings ont une sérialisation stable avec les champs attendus.
     *
     * Entrée : message = 5000 chars 'A' (déclenche un warning)
     * Résultat attendu : toArray() contient les clés 'field', 'type', 'original', 'fallback'
     */
    public function testItSupportsStableWarningSerialization(): void
    {
        $entry = LogEntryFactory::create(
            message: str_repeat(
                'A',
                5000,
            ),
        );

        $warnings = $entry
            ->getIngestionWarnings();

        self::assertNotEmpty(
            $warnings,
        );

        foreach ($warnings as $warning) {
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
        mixed $entry,
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