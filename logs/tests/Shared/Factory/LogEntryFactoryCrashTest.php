<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use App\Log\Domain\Exception\InvalidClientException;
use App\Log\Domain\Exception\InvalidFingerprintException;
use App\Log\Domain\Exception\InvalidHttpStatusException;
use App\Log\Domain\Exception\InvalidIpAddressException;
use App\Log\Domain\Exception\InvalidRequestException;
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
                $entry->message(),
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

    public function testItRejectsInvalidHttpStatus(): void
    {
        $this->expectException(
            InvalidHttpStatusException::class,
        );

        LogEntryFactory::create(
            httpStatus: -500,
        );
    }

    public function testItRejectsInvalidIp(): void
    {
        $this->expectException(
            InvalidIpAddressException::class,
        );

        LogEntryFactory::create(
            ip: 'invalid-ip',
        );
    }

    public function testItRejectsInvalidFingerprint(): void
    {
        $this->expectException(
            InvalidFingerprintException::class,
        );

        LogEntryFactory::create(
            fingerprint: 'INVALID',
        );
    }

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
            $entry->context(),
        );
    }

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
            $entry->extra(),
        );
    }

    public function testItSurvivesUnicodeMessage(): void
    {
        $entry = LogEntryFactory::create(
            message: 'Erreur 漢字 🚀 éàç',
        );

        self::assertSame(
            'Erreur 漢字 🚀 éàç',
            $entry->message(),
        );
    }

    public function testItSurvivesMassiveLoop(): void
    {
        for ($i = 0; $i < 2000; ++$i) {
            $entry = LogEntryFactory::create();

            self::assertNotEmpty(
                $entry->id(),
            );
        }
    }

    public function testItSurvivesMassiveFingerprintGeneration(): void
    {
        for ($i = 0; $i < 3000; ++$i) {
            $entry = LogEntryFactory::create(
                message: 'message-' . $i,
            );

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{16}$/',
                $entry
                    ->fingerprint()
                    ->value(),
            );
        }
    }

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
                    ->request()
                    ->userAgent(),
            ),
        );

        self::assertContainsWarningType(
            $entry,
            IngestionWarningType::USER_AGENT_TRUNCATED,
        );
    }

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
                ->request()
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

    public function testItSurvivesNegativeMany(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(-500),
        );
    }

    public function testItSurvivesZeroMany(): void
    {
        self::assertSame(
            [],
            LogEntryFactory::many(0),
        );
    }

    public function testItSupportsStableWarningSerialization(): void
    {
        $entry = LogEntryFactory::create(
            message: str_repeat(
                'A',
                5000,
            ),
        );

        $warnings = $entry
            ->ingestionWarnings();

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
            $entry->ingestionWarnings(),
        );

        self::assertContains(
            $expected->value,
            $types,
        );
    }
}