<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidFingerprintException;
use App\Log\Domain\ValueObject\Fingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Fingerprint.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir le format fingerprint
 * - garantir la stabilité des normalisations
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class FingerprintTest extends TestCase
{
    #[DataProvider('provideValidFingerprints')]
    /**
     * But : Vérifier que Fingerprint accepte des valeurs valides et les normalise correctement.
     *
     * Entrée : Cas fournis par le DataProvider `provideValidFingerprints()`
     * Résultat attendu : La valeur normalisée correspond à l'attendu du DataProvider
     */
    public function testItCreatesValidFingerprint(
        string $input,
        string $expected,
    ): void {
        $fingerprint = new Fingerprint($input);

        self::assertSame(
            $expected,
            $fingerprint->value(),
        );
    }

    #[DataProvider('provideInvalidFingerprints')]
    /**
     * But : Vérifier que Fingerprint rejette les valeurs invalides.
     *
     * Entrée : Cas fournis par le DataProvider `provideInvalidFingerprints()`
     * Résultat attendu : InvalidFingerprintException est levée
     */
    public function testItRejectsInvalidFingerprint(
        string $input,
    ): void {
        $this->expectException(
            InvalidFingerprintException::class,
        );

        new Fingerprint($input);
    }

    /**
     * But : Vérifier que fromExternal() crée un Fingerprint valide depuis une string externe.
     *
     * Entrée : 'abcdef1234567890'
     * Résultat attendu : Fingerprint avec valeur 'abcdef1234567890'
     */
    public function testItCreatesFromExternalString(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que le fingerprint est normalisé en minuscules.
     *
     * Entrée : 'ABCDEF1234567890'
     * Résultat attendu : 'abcdef1234567890'
     */
    public function testItNormalizesCase(): void
    {
        $fingerprint = new Fingerprint(
            'ABCDEF1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que le fingerprint est trimé et normalisé.
     *
     * Entrée : '   abcdef1234567890   '
     * Résultat attendu : 'abcdef1234567890'
     */
    public function testItNormalizesTrim(): void
    {
        $fingerprint = new Fingerprint(
            '   abcdef1234567890   ',
        );

        self::assertSame(
            'abcdef1234567890',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne le fallback '0000000000000000' pour une string invalide.
     *
     * Entrée : '<script>alert(1)</script>'
     * Résultat attendu : '0000000000000000'
     */
    public function testItFallsBackForInvalidString(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            '<script>alert(1)</script>',
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne le fallback '0000000000000000' pour null.
     *
     * Entrée : null
     * Résultat attendu : '0000000000000000'
     */
    public function testItFallsBackForNull(): void
    {
        $fingerprint = Fingerprint::fromExternal(null);

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne le fallback pour un type invalide.
     *
     * Entrée : ['invalid'] (tableau)
     * Résultat attendu : '0000000000000000'
     */
    public function testItFallsBackForInvalidType(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que isFallback() retourne true pour un fingerprint fallback.
     *
     * Entrée : fromExternal('<script>') → '0000000000000000'
     * Résultat attendu : isFallback() = true
     */
    public function testItDetectsFallbackFingerprint(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            '<script>',
        );

        self::assertTrue(
            $fingerprint->isFallback(),
        );
    }

    /**
     * But : Vérifier que isFallback() retourne false pour un fingerprint valide.
     *
     * Entrée : 'abcdef1234567890'
     * Résultat attendu : isFallback() = false
     */
    public function testItDetectsNonFallbackFingerprint(): void
    {
        $fingerprint = new Fingerprint(
            'abcdef1234567890',
        );

        self::assertFalse(
            $fingerprint->isFallback(),
        );
    }

    /**
     * But : Vérifier que equals() compare correctement deux Fingerprints.
     *
     * Entrée : Fingerprint('abcdef1234567890') vs le même, puis vs un autre
     * Résultat attendu : equals() = true / false selon les valeurs
     */
    public function testItComparesFingerprints(): void
    {
        $left = new Fingerprint(
            'abcdef1234567890',
        );

        $right = new Fingerprint(
            'abcdef1234567890',
        );

        $other = new Fingerprint(
            '1234567890abcdef',
        );

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne la valeur du fingerprint.
     *
     * Entrée : new Fingerprint('abcdef1234567890')
     * Résultat attendu : (string) Fingerprint = 'abcdef1234567890'
     */
    public function testItReturnsStableStringRepresentation(): void
    {
        $fingerprint = new Fingerprint(
            'abcdef1234567890',
        );

        self::assertSame(
            'abcdef1234567890',
            (string) $fingerprint,
        );
    }

    /**
     * But : Vérifier que Fingerprint::generate() est stable pour les mêmes paramètres.
     *
     * Entrée : Deux appels avec les mêmes inputs
     * Résultat attendu : Les valeurs générées sont identiques
     */
    public function testItGeneratesStableFingerprint(): void
    {
        $left = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        $right = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        self::assertTrue(
            $left->equals($right),
        );
    }

    /**
     * But : Vérifier que Fingerprint::generate() produit des valeurs différentes pour des inputs différents.
     *
     * Entrée : Inputs différents pour les deux appels
     * Résultat attendu : Les fingerprints générés diffèrent
     */
    public function testItGeneratesDifferentFingerprint(): void
    {
        $left = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        $right = Fingerprint::generate(
            'error|404|billing|/orders|prod',
        );

        self::assertFalse(
            $left->equals($right),
        );
    }

    /**
     * But : Vérifier que le fingerprint généré a exactement 16 caractères.
     *
     * Entrée : Paramètres valides pour generate()
     * Résultat attendu : Longueur = 16
     */
    public function testGeneratedFingerprintHasExpectedLength(): void
    {
        $fingerprint = Fingerprint::generate(
            'error|500|billing|/orders|prod',
        );

        self::assertSame(
            16,
            mb_strlen(
                $fingerprint->value(),
            ),
        );
    }

    /**
     * But : Vérifier que le fingerprint généré est en hexadécimal minuscule.
     *
     * Entrée : Paramètres valides pour generate()
     * Résultat attendu : Correspond au pattern /^[a-f0-9]{16}$/
     */
    public function testGeneratedFingerprintIsLowercase(): void
    {
        $fingerprint = Fingerprint::generate(
            'ERROR|500|BILLING|/ORDERS|PROD',
        );

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{16}$/',
            $fingerprint->value(),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Fingerprint valide
     */
    /**
     * But : Vérifier que fromExternal() ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 16 inputs hostiles variés
     * Résultat attendu : Aucune exception levée
     */
    public function testItNeverThrowsFromExternal(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            '',
            true,
            false,
            [],
            ['fingerprint'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '../../../../../etc/passwd',
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertInstanceOf(
                Fingerprint::class,
                $fingerprint,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne toujours un fingerprint de longueur 16 en hexadécimal.
     *
     * Entrée : 12 inputs variés
     * Résultat attendu : Longueur = 16, format hexadécimal
     */
    public function testFromExternalAlwaysReturnsValidFingerprint(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            true,
            false,
            [],
            new stdClass(),
            123,
            999999,
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertSame(
                16,
                mb_strlen(
                    $fingerprint->value(),
                ),
            );

            self::assertMatchesRegularExpression(
                '/^[a-f0-9]{16}$/',
                $fingerprint->value(),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne '0000000000000000' pour des payloads hostiles.
     *
     * Entrée : 8 inputs hostiles variés
     * Résultat attendu : '0000000000000000' pour chaque input
     */
    public function testItFallsBackForHostilePayloads(): void
    {
        $inputs = [
            null,
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
            "'; DROP TABLE logs; --",
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $fingerprint = Fingerprint::fromExternal($input);

            self::assertSame(
                '0000000000000000',
                $fingerprint->value(),
            );
        }
    }

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec un payload de 1 000 000 caractères.
     *
     * Entrée : str_repeat('A', 1000000)
     * Résultat attendu : Fingerprint créé ou fallback, aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'A',
            1000000,
        );

        $fingerprint = Fingerprint::fromExternal(
            $payload,
        );

        self::assertSame(
            '0000000000000000',
            $fingerprint->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() gère une séquence binaire sans exception.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Instance de Fingerprint créée (fallback ou valide)
     */
    public function testItHandlesBinaryPayload(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Fingerprint::class,
            $fingerprint,
        );
    }

    /**
     * But : Vérifier que fromExternal() gère une valeur UTF-8 invalide sans exception.
     *
     * Entrée : hex2bin('b131')
     * Résultat attendu : Instance de Fingerprint créée (fallback ou valide)
     */
    public function testItHandlesInvalidUtf8Payload(): void
    {
        $fingerprint = Fingerprint::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Fingerprint::class,
            $fingerprint,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidFingerprints(): iterable
    {
        yield 'lowercase' => [
            'abcdef1234567890',
            'abcdef1234567890',
        ];

        yield 'uppercase normalized' => [
            'ABCDEF1234567890',
            'abcdef1234567890',
        ];

        yield 'trimmed' => [
            '   abcdef1234567890   ',
            'abcdef1234567890',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidFingerprints(): iterable
    {
        yield 'empty string' => [''];

        yield 'too short' => [
            'abcdef',
        ];

        yield 'too long' => [
            'abcdef1234567890aaaa',
        ];

        yield 'invalid characters' => [
            'abcdef12345678$$',
        ];

        yield 'emoji' => [
            '🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥',
        ];

        yield 'slash' => [
            'abcd/efgh1234567',
        ];
    }
}