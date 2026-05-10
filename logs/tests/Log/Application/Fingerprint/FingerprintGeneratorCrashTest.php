<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Fingerprint;

use App\Log\Application\Fingerprint\FingerprintGenerator;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests critiques du FingerprintGenerator.
 *
 * Objectifs :
 * - garantir stabilité mémoire
 * - garantir robustesse hashing
 * - garantir absence de crash
 * - tester payloads extrêmes
 */
final class FingerprintGeneratorCrashTest extends TestCase
{
    private FingerprintGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FingerprintGenerator();
    }

    public function testItHandlesHugeDomain(): void
    {
        $domain = str_repeat(
            'billing-',
            100000,
        );

        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            $domain,
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotEmpty(
            $fingerprint->value(),
        );
    }

    public function testItHandlesHugeUri(): void
    {
        $uri = '/' . str_repeat(
            'orders/',
            10000,
        );

        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            Uri::fromExternal($uri),
            Environment::Production,
        );

        self::assertNotEmpty(
            $fingerprint->value(),
        );
    }

    public function testItHandlesBinaryPayloads(): void
    {
        $domain = "\x00\x01\x02";

        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            $domain,
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotEmpty(
            $fingerprint->value(),
        );
    }

    public function testItHandlesInvalidUtf8Payloads(): void
    {
        $domain = "\xB1\x31";

        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            $domain,
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotEmpty(
            $fingerprint->value(),
        );
    }

    public function testItHandlesHostilePayloads(): void
    {
        $domain = "'; DROP TABLE logs; --";

        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            $domain,
            new Uri('/../../../../../etc/passwd'),
            Environment::Production,
        );

        self::assertNotEmpty(
            $fingerprint->value(),
        );
    }

    public function testItNeverThrows(): void
    {
        for ($i = 0; $i < 10000; $i++) {
            $fingerprint = $this->generator->generate(
                LogLevel::ERROR,
                new HttpStatus(500),
                'billing-' . $i,
                new Uri('/orders/' . $i),
                Environment::Production,
            );

            self::assertNotEmpty(
                $fingerprint->value(),
            );
        }
    }
}