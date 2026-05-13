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

    /**
     * But : Vérifier que generate() retourne bien une instance de Fingerprint.
     *
     * Entrée : LogLevel::ERROR, HttpStatus(500), 'billing', Uri('/orders'), Environment::Production
     * Résultat attendu : Instance de Fingerprint retournée
     */
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

    /**
     * But : Vérifier que deux appels identiques produisent le même fingerprint.
     *
     * Entrée : Deux appels identiques (ERROR, 500, 'billing', '/orders', Production)
     * Résultat attendu : Les deux valeurs de fingerprint sont égales
     */
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

    /**
     * But : Vérifier que des niveaux de log différents produisent des fingerprints distincts.
     *
     * Entrée : ERROR vs WARNING, tous autres paramètres identiques
     * Résultat attendu : Les fingerprints diffèrent
     */
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

    /**
     * But : Vérifier que des statuts HTTP différents produisent des fingerprints distincts.
     *
     * Entrée : HttpStatus(404) vs HttpStatus(500), tous autres paramètres identiques
     * Résultat attendu : Les fingerprints diffèrent
     */
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

    /**
     * But : Vérifier que des domaines différents produisent des fingerprints distincts.
     *
     * Entrée : 'billing' vs 'checkout', tous autres paramètres identiques
     * Résultat attendu : Les fingerprints diffèrent
     */
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

    /**
     * But : Vérifier que des URI différentes produisent des fingerprints distincts.
     *
     * Entrée : Uri('/orders') vs Uri('/users'), tous autres paramètres identiques
     * Résultat attendu : Les fingerprints diffèrent
     */
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

    /**
     * But : Vérifier que des environnements différents produisent des fingerprints distincts.
     *
     * Entrée : Environment::Production vs Environment::Development, tous autres paramètres identiques
     * Résultat attendu : Les fingerprints diffèrent
     */
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

    /**
     * But : Vérifier que la normalisation du domaine rend les fingerprints insensibles à la casse.
     *
     * Entrée : ' BILLING ' vs 'billing', tous autres paramètres identiques
     * Résultat attendu : Les fingerprints sont identiques
     */
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

    /**
     * But : Vérifier que le fingerprint a toujours une longueur fixe de 16 caractères.
     *
     * Entrée : LogLevel::ERROR, HttpStatus(500), 'billing', Uri('/orders'), Environment::Production
     * Résultat attendu : Longueur du fingerprint = 16
     */
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

    /**
     * But : Vérifier que le fingerprint est uniquement composé de caractères hexadécimaux minuscules.
     *
     * Entrée : LogLevel::ERROR, HttpStatus(500), 'billing', Uri('/orders'), Environment::Production
     * Résultat attendu : Correspond au pattern /^[a-f0-9]{16}$/
     */
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
