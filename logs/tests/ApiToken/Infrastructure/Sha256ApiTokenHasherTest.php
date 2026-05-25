<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Infrastructure;

use App\ApiToken\Infrastructure\Sha256ApiTokenHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests du hasher SHA-256
 * pour tokens API.
 */
final class Sha256ApiTokenHasherTest extends TestCase
{
    /**
     * But : Vérifier que hash() retourne un SHA-256 hexadécimal stable.
     *
     * Entrée : token opaque non vide.
     * Résultat attendu : 64 caractères hexa et valeur déterministe.
     */
    public function testHashReturnsDeterministicSha256(): void
    {
        $hasher = new Sha256ApiTokenHasher();

        $hash = $hasher->hash(
            'cbi_token_for_tests',
        );

        self::assertSame(
            hash('sha256', 'cbi_token_for_tests'),
            $hash,
        );

        self::assertSame(
            64,
            strlen($hash),
        );
    }

    /**
     * But : Vérifier que hash() refuse un token vide.
     *
     * Entrée : chaîne vide.
     * Résultat attendu : InvalidArgumentException explicite.
     */
    public function testHashRejectsEmptyToken(): void
    {
        $hasher = new Sha256ApiTokenHasher();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $this->expectExceptionMessage(
            'Plain token cannot be empty.',
        );

        $hasher->hash('   ');
    }
}
