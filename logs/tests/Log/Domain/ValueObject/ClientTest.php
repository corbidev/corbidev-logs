<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidClientException;
use App\Log\Domain\ValueObject\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Client.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les normalisations
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class ClientTest extends TestCase
{
    #[DataProvider('provideValidClients')]
    /**
     * But : Vérifier que Client accepte des valeurs valides et les normalise correctement.
     *
     * Entrée : Cas fournis par le DataProvider `provideValidClients()`
     * Résultat attendu : La valeur normalisée correspond à l'attendu du DataProvider
     */
    public function testItCreatesValidClient(
        string $input,
        string $expected,
    ): void {
        $client = new Client($input);

        self::assertSame(
            $expected,
            $client->value(),
        );
    }

    #[DataProvider('provideInvalidClients')]
    /**
     * But : Vérifier que Client rejette les valeurs invalides.
     *
     * Entrée : Cas fournis par le DataProvider `provideInvalidClients()`
     * Résultat attendu : InvalidClientException est levée
     */
    public function testItRejectsInvalidClient(
        string $input,
    ): void {
        $this->expectException(
            InvalidClientException::class,
        );

        new Client($input);
    }

    /**
     * But : Vérifier que fromExternal() crée un Client valide depuis une string externe.
     *
     * Entrée : 'checkout-service'
     * Résultat attendu : Client avec valeur 'checkout-service'
     */
    public function testItCreatesFromExternalString(): void
    {
        $client = Client::fromExternal(
            'checkout-service',
        );

        self::assertSame(
            'checkout-service',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que le nom du client est normalisé en minuscules.
     *
     * Entrée : 'CHECKOUT_SERVICE'
     * Résultat attendu : 'checkout_service'
     */
    public function testItNormalizesCase(): void
    {
        $client = new Client(
            'CHECKOUT_SERVICE',
        );

        self::assertSame(
            'checkout_service',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que les espaces dans le nom du client sont remplacés par des tirets.
     *
     * Entrée : 'My Awesome App'
     * Résultat attendu : 'my-awesome-app'
     */
    public function testItNormalizesSpaces(): void
    {
        $client = new Client(
            'My Awesome App',
        );

        self::assertSame(
            'my-awesome-app',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que le nom du client est trimé et normalisé.
     *
     * Entrée : '   Checkout-App   '
     * Résultat attendu : 'checkout-app'
     */
    public function testItNormalizesTrim(): void
    {
        $client = new Client(
            '   Checkout-App   ',
        );

        self::assertSame(
            'checkout-app',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 'unknown' pour une string invalide.
     *
     * Entrée : '<script>alert(1)</script>'
     * Résultat attendu : 'unknown'
     */
    public function testItFallsBackForInvalidString(): void
    {
        $client = Client::fromExternal(
            '<script>alert(1)</script>',
        );

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 'unknown' pour null.
     *
     * Entrée : null
     * Résultat attendu : 'unknown'
     */
    public function testItFallsBackForNull(): void
    {
        $client = Client::fromExternal(null);

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 'unknown' pour un type invalide.
     *
     * Entrée : ['invalid'] (tableau)
     * Résultat attendu : 'unknown'
     */
    public function testItFallsBackForInvalidType(): void
    {
        $client = Client::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que isUnknown() retourne true pour un client créé via fromExternal() avec valeur hostile.
     *
     * Entrée : fromExternal('<script>alert(1)</script>')
     * Résultat attendu : isUnknown() = true
     */
    public function testItDetectsUnknownClient(): void
    {
        $client = Client::fromExternal(
            '<script>',
        );

        self::assertTrue(
            $client->isUnknown(),
        );
    }

    /**
     * But : Vérifier que isUnknown() retourne false pour un client valide.
     *
     * Entrée : new Client('checkout-service')
     * Résultat attendu : isUnknown() = false
     */
    public function testItDetectsKnownClient(): void
    {
        $client = new Client(
            'checkout-service',
        );

        self::assertFalse(
            $client->isUnknown(),
        );
    }

    /**
     * But : Vérifier que equals() compare correctement deux instances de Client.
     *
     * Entrée : Client('checkout') vs Client('checkout'), puis vs Client('billing')
     * Résultat attendu : equals() = true / false selon les valeurs
     */
    public function testItComparesClients(): void
    {
        $left = new Client('checkout');
        $right = new Client('checkout');
        $other = new Client('billing');

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne la valeur normalisée du client.
     *
     * Entrée : new Client('checkout-service')
     * Résultat attendu : (string) Client = 'checkout-service'
     */
    public function testItReturnsStableStringRepresentation(): void
    {
        $client = new Client(
            'checkout-service',
        );

        self::assertSame(
            'checkout-service',
            (string) $client,
        );
    }

    /**
     * But : Vérifier qu'un client de 100 caractères (longueur maximale) est accepté.
     *
     * Entrée : str_repeat('a', 100)
     * Résultat attendu : Client créé sans exception
     */
    public function testItSupportsMaximumLengthBoundary(): void
    {
        $value = str_repeat('a', 100);

        $client = new Client($value);

        self::assertSame(
            $value,
            $client->value(),
        );
    }

    /**
     * But : Vérifier que Client rejette une valeur dépassant 100 caractères.
     *
     * Entrée : str_repeat('a', 101)
     * Résultat attendu : InvalidClientException est levée
     */
    public function testItRejectsTooLongClient(): void
    {
        $this->expectException(
            InvalidClientException::class,
        );

        new Client(
            str_repeat('a', 101),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Client valide
     */
    /**
     * But : Vérifier que fromExternal() ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 18 inputs hostiles variés (XSS, SQL, binaire, null, etc.)
     * Résultat attendu : Aucune exception levée
     */
    public function testItNeverThrowsFromExternal(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $inputs = [
            null,
            '',
            '   ',
            true,
            false,
            0,
            1,
            [],
            ['client'],
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
            $client = Client::fromExternal($input);

            self::assertInstanceOf(
                Client::class,
                $client,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne toujours une valeur valide non vide ≤ 100 chars.
     *
     * Entrée : 12 inputs variés
     * Résultat attendu : Valeur non vide, longueur ≤ 100
     */
    public function testFromExternalAlwaysReturnsValidClient(): void
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
            $client = Client::fromExternal($input);

            self::assertNotSame(
                '',
                $client->value(),
            );

            self::assertLessThanOrEqual(
                100,
                mb_strlen($client->value()),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne 'unknown' pour les payloads hostiles.
     *
     * Entrée : 9 inputs hostiles (injections, binaire, etc.)
     * Résultat attendu : 'unknown' pour chaque input
     */
    public function testItFallsBackForHostilePayloads(): void
    {
        $inputs = [
            null,
            '',
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
            "'; DROP TABLE logs; --",
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $client = Client::fromExternal($input);

            self::assertSame(
                'unknown',
                $client->value(),
            );
        }
    }

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec un payload de 600 000 caractères.
     *
     * Entrée : str_repeat('CLIENT', 100000)
     * Résultat attendu : 'unknown' retourné, aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('CLIENT', 100000);

        $client = Client::fromExternal($payload);

        self::assertSame(
            'unknown',
            $client->value(),
        );
    }

    /**
     * But : Vérifier que Client accepte un payload binaire sans crash.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Instance Client créée ou fallback, aucune exception
     */
    public function testItHandlesBinaryPayload(): void
    {
        $client = Client::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Client::class,
            $client,
        );
    }

    /**
     * But : Vérifier que Client accepte de l'UTF-8 invalide sans crash.
     *
     * Entrée : hex2bin('b131') (UTF-8 invalide)
     * Résultat attendu : Instance Client créée ou fallback, aucune exception
     */
    public function testItHandlesInvalidUtf8Payload(): void
    {
        $client = Client::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Client::class,
            $client,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidClients(): iterable
    {
        yield 'simple' => [
            'checkout',
            'checkout',
        ];

        yield 'dash separated' => [
            'checkout-service',
            'checkout-service',
        ];

        yield 'underscore separated' => [
            'CHECKOUT_SERVICE',
            'checkout_service',
        ];

        yield 'dot separated' => [
            'api.gateway.v2',
            'api.gateway.v2',
        ];

        yield 'spaces normalized' => [
            'My Awesome App',
            'my-awesome-app',
        ];

        yield 'trimmed' => [
            '   Billing-App   ',
            'billing-app',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidClients(): iterable
    {
        yield 'empty string' => [''];

        yield 'spaces only' => ['   '];

        yield 'invalid characters' => [
            '<script>',
        ];

        yield 'emoji' => [
            '🔥app',
        ];

        yield 'slash' => [
            'app/backend',
        ];

        yield 'too long' => [
            str_repeat('a', 101),
        ];
    }
}