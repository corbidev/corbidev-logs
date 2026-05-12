<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use PHPUnit\Framework\TestCase;

/**
 * Crash tests de LogEntryFactory.
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

    public function testItRejectsVeryLongMessage(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            message: str_repeat(
                'a',
                5000,
            ),
        );
    }

    public function testItRejectsInvalidHttpStatus(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            httpStatus: -500,
        );
    }

    public function testItRejectsInvalidIp(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            ip: 'invalid-ip',
        );
    }

    public function testItRejectsInvalidFingerprint(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            fingerprint: 'INVALID',
        );
    }

    public function testItSurvivesHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 5000; ++$i) {
            $context['key_'.$i] = $i;
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
            $extra['extra_'.$i] = $i;
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
                message: 'message-'.$i,
            );

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{16}$/',
                $entry->fingerprint()->value(),
            );
        }
    }

    public function testItRejectsHugeUri(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            uri: '/'
                . str_repeat(
                    'segment/',
                    300,
                ),
        );
    }

    public function testItRejectsHugeUserAgent(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            userAgent: str_repeat(
                'Mozilla/5.0 ',
                500,
            ),
        );
    }

    public function testItRejectsHugeClientName(): void
    {
        $this->expectException(
            \Throwable::class,
        );

        LogEntryFactory::create(
            client: str_repeat(
                'phpunit-client-',
                100,
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
}