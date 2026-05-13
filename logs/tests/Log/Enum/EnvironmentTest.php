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
    /**
     * But : Vérifier que les valeurs production sont correctement reconnues.
     *
     * Entrée : Cas fournis par le DataProvider `provideProductionValues()`
     * Résultat attendu : Environment::Production retourné pour chaque cas
     */
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
    /**
     * But : Vérifier que les valeurs staging sont correctement reconnues.
     *
     * Entrée : Cas fournis par le DataProvider `provideStagingValues()`
     * Résultat attendu : Environment::Staging retourné pour chaque cas
     */
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
    /**
     * But : Vérifier que les valeurs development sont correctement reconnues.
     *
     * Entrée : Cas fournis par le DataProvider `provideDevelopmentValues()`
     * Résultat attendu : Environment::Development retourné pour chaque cas
     */
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
    /**
     * But : Vérifier que les valeurs test sont correctement reconnues.
     *
     * Entrée : Cas fournis par le DataProvider `provideTestValues()`
     * Résultat attendu : Environment::Test retourné pour chaque cas
     */
    public function testItCreatesTestEnvironment(
        string $input,
    ): void {
        $environment = Environment::fromExternal($input);

        self::assertSame(
            Environment::Test,
            $environment,
        );
    }

    /**
     * But : Vérifier que la factory retourne Production pour une valeur inconnue.
     *
     * Entrée : 'totally-unknown-environment'
     * Résultat attendu : Environment::Production
     */
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

    /**
     * But : Vérifier que la factory retourne Production pour une chaîne vide.
     *
     * Entrée : ''
     * Résultat attendu : Environment::Production
     */
    public function testItFallsBackToProductionForEmptyString(): void
    {
        $environment = Environment::fromExternal('');

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    /**
     * But : Vérifier que la factory normalise les espaces et la casse.
     *
     * Entrée : '   PROD   '
     * Résultat attendu : Environment::Production
     */
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

    /**
     * But : Vérifier que isProduction() retourne true pour Production, false pour les autres.
     *
     * Entrée : Environment::Production et Environment::Staging
     * Résultat attendu : isProduction() = true / false selon l'environnement
     */
    public function testItDetectsProductionEnvironment(): void
    {
        self::assertTrue(
            Environment::Production->isProduction(),
        );

        self::assertFalse(
            Environment::Development->isProduction(),
        );
    }

    /**
     * But : Vérifier que isDevelopment() retourne true pour Development, false pour les autres.
     *
     * Entrée : Environment::Development et Environment::Production
     * Résultat attendu : isDevelopment() = true / false selon l'environnement
     */
    public function testItDetectsDevelopmentEnvironment(): void
    {
        self::assertTrue(
            Environment::Development->isDevelopment(),
        );

        self::assertFalse(
            Environment::Production->isDevelopment(),
        );
    }

    /**
     * But : Vérifier que isStaging() retourne true pour Staging, false pour les autres.
     *
     * Entrée : Environment::Staging et Environment::Production
     * Résultat attendu : isStaging() = true / false selon l'environnement
     */
    public function testItDetectsStagingEnvironment(): void
    {
        self::assertTrue(
            Environment::Staging->isStaging(),
        );

        self::assertFalse(
            Environment::Production->isStaging(),
        );
    }

    /**
     * But : Vérifier que isTest() retourne true pour Test, false pour les autres.
     *
     * Entrée : Environment::Test et Environment::Production
     * Résultat attendu : isTest() = true / false selon l'environnement
     */
    public function testItDetectsTestEnvironment(): void
    {
        self::assertTrue(
            Environment::Test->isTest(),
        );

        self::assertFalse(
            Environment::Production->isTest(),
        );
    }

    /**
     * But : Vérifier que getSupportedValues() retourne toutes les valeurs supportées.
     *
     * Entrée : Appel statique sans paramètre
     * Résultat attendu : ['prod', 'staging', 'dev', 'test']
     */
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
    /**
     * But : Vérifier que la factory ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 18 inputs hostiles variés (XSS, SQL injection, null, tableaux, etc.)
     * Résultat attendu : Aucune exception levée, retour toujours Environment::Production
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

    /**
     * But : Vérifier que la factory retourne Production pour les inputs hostiles courants.
     *
     * Entrée : 7 inputs hostiles (null, '', 'unknown', binaire, XSS, SQL injection)
     * Résultat attendu : Environment::Production pour chaque input
     */
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

    /**
     * But : Vérifier que la factory ne crashe pas avec un payload de 1 000 000 caractères.
     *
     * Entrée : str_repeat('PRODUCTION', 100000)
     * Résultat attendu : Environment::Production (fallback), aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('PRODUCTION', 100000);

        $environment = Environment::fromExternal($payload);

        self::assertSame(
            Environment::Production,
            $environment,
        );
    }

    /**
     * But : Vérifier que la factory gère une séquence binaire sans exception.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Instance de Environment créée (Production ou autre)
     */
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

    /**
     * But : Vérifier que la factory gère une valeur UTF-8 invalide sans exception.
     *
     * Entrée : "\xB1\x31"
     * Résultat attendu : Instance de Environment créée (Production ou autre)
     */
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