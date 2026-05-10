<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Factory;

use App\Log\Application\Factory\LogEntryFactory;
use App\Log\Domain\Entity\LogEntry;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Crash tests critiques de LogEntryFactory.
 *
 * Objectifs :
 * - garantir robustesse ingestion
 * - garantir absence de crash
 * - garantir stabilité mémoire
 * - tester payloads hostiles
 */
final class LogEntryFactoryCrashTest extends TestCase
{
    private LogEntryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LogEntryFactory();
    }

    public function testItNeverThrowsWithHostilePayloads(): void
    {
        $resource = fopen(
            'php://memory',
            'r',
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
                'context' => $resource,
            ],
            [
                'extra' => $resource,
            ],
            [
                'tags' => $resource,
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

        fclose($resource);
    }

    public function testItHandlesHugeContext(): void
    {
        $context = [];

        for ($i = 0; $i < 10000; $i++) {
            $context['key-' . $i] = str_repeat(
                'A',
                1000,
            );
        }

        $entry = $this->factory->create([
            'context' => $context,
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesHugeExtra(): void
    {
        $extra = [];

        for ($i = 0; $i < 10000; $i++) {
            $extra['key-' . $i] = str_repeat(
                'B',
                1000,
            );
        }

        $entry = $this->factory->create([
            'extra' => $extra,
        ]);

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }

    public function testItHandlesHugeTags(): void
    {
        $tags = [];

        for ($i = 0; $i < 1000; $i++) {
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

        self::assertInstanceOf(
            LogEntry::class,
            $entry,
        );
    }
}