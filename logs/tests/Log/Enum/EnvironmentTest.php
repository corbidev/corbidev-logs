<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Enum;

use App\Log\Enum\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires de l'enum Environment.
 *
 * Objectifs :
 * - garantir les aliases supportés
 * - garantir les fallbacks ingestion
 * - garantir la stabilité des helpers
 * - garantir la robustesse face aux payloads hostiles
 */
final class EnvironmentTest extends TestCase
{
    #[DataProvider('provideProductionValues')]
    public function testItCreatesProductionEnvironment(
        string|null $input,
    ): void {
        $environment = Environment::fromExternal($input);

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    #[DataProvider('provideStagingValues')]
    public function testItCreatesStagingEnvironment(
        string $input,
    ): void {
        $environment = Environment::fromExternal($input);

        self::assertSame(
            Environment::Staging,
            $environment,
        );
    }

    #[DataProvider('provideDevelopmentValues')]
    public function testItCreatesDevelopmentEnvironment(
        string $input,
    ): void {
        $environment = Environment::fromExternal($input);

        self::assertSame(
            Environment::Development,
            $environment,
        );
    }

    #[DataProvider('provideTestValues')]
    public function testItCreatesTestEnvironment(
        string $input,
    ): void {
        $environment = Environment::fromExternal($input);

        self::assertSame(
            Environment::Test,
            $environment,
        );
    }

    public function testItFallsBackToProductionForUnknownValue(): void
    {
        $environment = Environment::fromExternal(
            'totally-unknown-environment',
        );

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    public function testItFallsBackToProductionForEmptyString(): void
    {
        $environment = Environment::fromExternal('');

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    public function testItNormalizesTrimAndCase(): void
    {
        $environment = Environment::fromExternal(
            '   PROD   ',
        );

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    public function testItDetectsProductionEnvironment(): void
    {
        self::assertTrue(
            Environment::Production->isProduction(),
        );

        self::assertFalse(
            Environment::Development->isProduction(),
        );
    }

    public function testItDetectsDevelopmentEnvironment(): void
    {
        self::assertTrue(
            Environment::Development->isDevelopment(),
        );

        self::assertFalse(
            Environment::Production->isDevelopment(),
        );
    }

    public function testItDetectsStagingEnvironment(): void
    {
        self::assertTrue(
            Environment::Staging->isStaging(),
        );

        self::assertFalse(
            Environment::Production->isStaging(),
        );
    }

    public function testItDetectsTestEnvironment(): void
    {
        self::assertTrue(
            Environment::Test->isTest(),
        );

        self::assertFalse(
            Environment::Production->isTest(),
        );
    }

    public function testItReturnsSupportedValues(): void
    {
        self::assertSame(
            [
                'prod',
                'staging',
                'dev',
                'test',
            ],
            Environment::values(),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un enum valide
     */
    public function testItNeverThrowsForHostileInputs(): void
    {
        $resource = fopen('php://memory', 'r');

        $inputs = [
            null,
            '',
            '   ',
            'unknown',
            true,
            false,
            0,
            1,
            999999,
            [],
            ['prod'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            "\xB1\x31",
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
        ];

        foreach ($inputs as $input) {
            $environment = Environment::fromExternal(
                is_string($input) || $input === null
                    ? $input
                    : json_encode($input),
            );

            self::assertInstanceOf(
                Environment::class,
                $environment,
            );
        }

        fclose($resource);
    }

    public function testItFallsBackToProductionForHostilePayloads(): void
    {
        $inputs = [
            null,
            '',
            'unknown',
            "\x00\x01",
            "\xB1\x31",
            '<script>',
            "'; DROP TABLE logs; --",
        ];

        foreach ($inputs as $input) {
            $environment = Environment::fromExternal($input);

            self::assertSame(
                Environment::Production,
                $environment,
            );
        }
    }

    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('PRODUCTION', 100000);

        $environment = Environment::fromExternal($payload);

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    public function testItHandlesBinaryPayload(): void
    {
        $environment = Environment::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Environment::class,
            $environment,
        );
    }

    public function testItHandlesInvalidUtf8Payload(): void
    {
        $environment = Environment::fromExternal(
            "\xB1\x31",
        );

        self::assertInstanceOf(
            Environment::class,
            $environment,
        );
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function provideProductionValues(): iterable
    {
        yield 'prod' => ['prod'];

        yield 'production' => ['production'];

        yield 'live' => ['live'];

        yield 'uppercase' => ['PROD'];

        yield 'trimmed' => ['   prod   '];

        yield 'null fallback' => [null];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideStagingValues(): iterable
    {
        yield 'staging' => ['staging'];

        yield 'stage' => ['stage'];

        yield 'preprod' => ['preprod'];

        yield 'pre-production' => ['pre-production'];

        yield 'preproduction' => ['preproduction'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideDevelopmentValues(): iterable
    {
        yield 'dev' => ['dev'];

        yield 'development' => ['development'];

        yield 'local' => ['local'];

        yield 'localhost' => ['localhost'];

        yield 'uppercase' => ['DEV'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideTestValues(): iterable
    {
        yield 'test' => ['test'];

        yield 'testing' => ['testing'];

        yield 'tests' => ['tests'];

        yield 'ci' => ['ci'];
    }
}