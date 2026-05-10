<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Application\Fingerprint;

use App\Log\Application\Fingerprint\FingerprintGenerator;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires du FingerprintGenerator.
 */
final class FingerprintGeneratorTest extends TestCase
{
    private FingerprintGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FingerprintGenerator();
    }

    public function testItGeneratesFingerprint(): void
    {
        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertInstanceOf(
            Fingerprint::class,
            $fingerprint,
        );
    }

    public function testItGeneratesStableFingerprint(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItGeneratesDifferentFingerprintForDifferentLevel(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::WARNING,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItGeneratesDifferentFingerprintForDifferentStatus(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(404),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItGeneratesDifferentFingerprintForDifferentDomain(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'checkout',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertNotSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItGeneratesDifferentFingerprintForDifferentUri(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/users'),
            Environment::Production,
        );

        self::assertNotSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItGeneratesDifferentFingerprintForDifferentEnvironment(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Development,
        );

        self::assertNotSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItNormalizesDomain(): void
    {
        $left = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            ' BILLING ',
            new Uri('/orders'),
            Environment::Production,
        );

        $right = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertSame(
            $left->value(),
            $right->value(),
        );
    }

    public function testItReturnsFixedLengthFingerprint(): void
    {
        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertSame(
            16,
            mb_strlen(
                $fingerprint->value(),
            ),
        );
    }

    public function testItReturnsHexadecimalFingerprint(): void
    {
        $fingerprint = $this->generator->generate(
            LogLevel::ERROR,
            new HttpStatus(500),
            'billing',
            new Uri('/orders'),
            Environment::Production,
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $fingerprint->value(),
        );
    }
}