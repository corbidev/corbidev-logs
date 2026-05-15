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

    /**
     * But : Vérifier que le générateur ne crashe pas avec un domaine de 800 000 caractères.
     *
     * Entrée : Domaine = str_repeat('billing-', 100000)
     * Résultat attendu : Fingerprint non vide retourné sans exception
     */
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

    /**
     * But : Vérifier que le générateur ne crashe pas avec une URI de 80 000 segments.
     *
     * Entrée : URI = '/' . str_repeat('orders/', 10000)
     * Résultat attendu : Fingerprint non vide retourné sans exception
     */
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

    /**
     * But : Vérifier que le générateur ne crashe pas avec un domaine contenant des octets binaires.
     *
     * Entrée : Domaine = "\x00\x01\x02"
     * Résultat attendu : Fingerprint non vide retourné sans exception
     */
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

    /**
     * But : Vérifier que le générateur ne crashe pas avec un domaine en UTF-8 invalide.
     *
     * Entrée : Domaine = "\xB1\x31" (séquence UTF-8 invalide)
     * Résultat attendu : Fingerprint non vide retourné sans exception
     */
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

    /**
     * But : Vérifier que le générateur ne crashe pas avec des payloads d'injection hostiles.
     *
     * Entrée : Domaine = "'; DROP TABLE logs; --", URI = "/../../../../../etc/passwd"
     * Résultat attendu : Fingerprint non vide retourné sans exception
     */
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

    /**
     * But : Vérifier que 10 000 appels consécutifs ne lèvent jamais d'exception.
     *
     * Entrée : 10 000 itérations avec domaines 'billing-{i}' et URI '/orders/{i}'
     * Résultat attendu : Chaque fingerprint est non vide, aucune exception levée
     */
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