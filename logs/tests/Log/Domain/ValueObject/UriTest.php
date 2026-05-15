<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidUriException;
use App\Log\Domain\ValueObject\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Uri.
 *
 * Objectifs :
 * - garantir la stabilité des normalisations
 * - garantir les invariants métier
 * - garantir la suppression des query strings
 * - verrouiller les comportements hostiles
 * - garantir la robustesse ingestion
 */
final class UriTest extends TestCase
{
    #[DataProvider('provideValidUris')]
    /**
     * But : Vérifier que Uri accepte des URIs valides.
     *
     * Entrée : Cas fournis par le DataProvider `provideValidUris()`
     * Résultat attendu : Uri créée sans exception
     */
    public function testItCreatesValidUri(
        string $input,
        string $expected,
    ): void {
        $uri = new Uri($input);

        self::assertSame(
            $expected,
            $uri->value(),
        );
    }

    #[DataProvider('provideInvalidUris')]
    /**
     * But : Vérifier que Uri rejette les URIs invalides.
     *
     * Entrée : Cas fournis par le DataProvider `provideInvalidUris()`
     * Résultat attendu : InvalidUriException est levée
     */
    public function testItRejectsInvalidUri(
        string $input,
    ): void {
        $this->expectException(
            InvalidUriException::class,
        );

        new Uri($input);
    }

    /**
     * But : Vérifier que fromExternal() crée une Uri depuis une string externe.
     *
     * Entrée : '/api/logs'
     * Résultat attendu : Uri avec valeur '/api/logs'
     */
    public function testItCreatesUriFromExternalString(): void
    {
        $uri = Uri::fromExternal('/orders');

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour null.
     *
     * Entrée : null
     * Résultat attendu : '/'
     */
    public function testItFallsBackToRootForNull(): void
    {
        $uri = Uri::fromExternal(null);

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour un type invalide.
     *
     * Entrée : ['invalid'] (tableau)
     * Résultat attendu : '/'
     */
    public function testItFallsBackToRootForInvalidType(): void
    {
        $uri = Uri::fromExternal(
            ['invalid'],
        );

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour une URI dépassant la longueur max.
     *
     * Entrée : URI de 2001 caractères
     * Résultat attendu : '/'
     */
    public function testItFallsBackToRootForInvalidUri(): void
    {
        $uri = Uri::fromExternal(
            str_repeat('a', 3000),
        );

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que la query string est supprimée de l'URI.
     *
     * Entrée : '/orders?page=1&sort=asc'
     * Résultat attendu : '/orders'
     */
    public function testItRemovesQueryString(): void
    {
        $uri = new Uri(
            '/orders?id=42&token=secret',
        );

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que le fragment est supprimé de l'URI.
     *
     * Entrée : '/orders#section1'
     * Résultat attendu : '/orders'
     */
    public function testItRemovesFragment(): void
    {
        $uri = new Uri(
            '/orders#create',
        );

        self::assertSame(
            '/orders',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que les slashes doublons dans l'URI sont normalisés.
     *
     * Entrée : '//orders//billing'
     * Résultat attendu : '/orders/billing'
     */
    public function testItNormalizesDuplicateSlashes(): void
    {
        $uri = new Uri(
            '///orders///create///',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() extrait le path d'une URL complète.
     *
     * Entrée : 'https://api.example.com/orders?page=1'
     * Résultat attendu : '/orders'
     */
    public function testItNormalizesFullUrl(): void
    {
        $uri = new Uri(
            'https://example.com/orders/create?id=42',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que le slash initial est ajouté si absent.
     *
     * Entrée : 'orders/billing'
     * Résultat attendu : '/orders/billing'
     */
    public function testItNormalizesMissingLeadingSlash(): void
    {
        $uri = new Uri(
            'orders/create',
        );

        self::assertSame(
            '/orders/create',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour une chaîne vide.
     *
     * Entrée : ''
     * Résultat attendu : '/'
     */
    public function testItNormalizesEmptyStringToRoot(): void
    {
        $uri = new Uri('');

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que isRoot() retourne true pour '/'.
     *
     * Entrée : '/'
     * Résultat attendu : isRoot() = true
     */
    public function testItDetectsRootUri(): void
    {
        $uri = new Uri('/');

        self::assertTrue(
            $uri->isRoot(),
        );

        self::assertFalse(
            $uri->hasSegments(),
        );
    }

    /**
     * But : Vérifier que isRoot() retourne false pour une URI avec des segments.
     *
     * Entrée : '/orders/billing'
     * Résultat attendu : isRoot() = false
     */
    public function testItDetectsUriWithSegments(): void
    {
        $uri = new Uri('/orders/create');

        self::assertFalse(
            $uri->isRoot(),
        );

        self::assertTrue(
            $uri->hasSegments(),
        );
    }

    /**
     * But : Vérifier que segments() retourne les segments de l'URI.
     *
     * Entrée : '/orders/billing/123'
     * Résultat attendu : ['orders', 'billing', '123']
     */
    public function testItReturnsSegments(): void
    {
        $uri = new Uri('/orders/create');

        self::assertSame(
            [
                'orders',
                'create',
            ],
            $uri->segments(),
        );
    }

    /**
     * But : Vérifier que segments() retourne un tableau vide pour '/'.
     *
     * Entrée : '/'
     * Résultat attendu : []
     */
    public function testItReturnsEmptySegmentsForRoot(): void
    {
        $uri = new Uri('/');

        self::assertSame(
            [],
            $uri->segments(),
        );
    }

    /**
     * But : Vérifier que equals() compare correctement deux Uri.
     *
     * Entrée : Uri('/orders') vs Uri('/orders'), puis vs Uri('/billing')
     * Résultat attendu : equals() = true / false
     */
    public function testItComparesUris(): void
    {
        $left = new Uri('/orders');
        $right = new Uri('/orders');
        $other = new Uri('/users');

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne la valeur de l'URI.
     *
     * Entrée : Uri('/orders')
     * Résultat attendu : (string) Uri = '/orders'
     */
    public function testItReturnsStableStringRepresentation(): void
    {
        $uri = new Uri('/orders');

        self::assertSame(
            '/orders',
            (string) $uri,
        );
    }

    /**
     * But : Vérifier que les caractères encodés en URL sont décodés dans l'URI.
     *
     * Entrée : '/orders%2Fbilling'
     * Résultat attendu : '/orders/billing'
     */
    public function testItDecodesEncodedCharacters(): void
    {
        $uri = new Uri(
            '/search/%E2%82%AC',
        );

        self::assertSame(
            '/search/€',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier qu'une URI de 2 000 caractères (longueur maximale) est acceptée.
     *
     * Entrée : '/' . str_repeat('a', 1999)
     * Résultat attendu : Uri créée sans exception
     */
    public function testItSupportsMaximumLengthBoundary(): void
    {
        $path = '/' . str_repeat('a', 2047);

        $uri = new Uri($path);

        self::assertSame(
            $path,
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que Uri rejette une URI dépassant 2 000 caractères.
     *
     * Entrée : '/' . str_repeat('a', 2000)
     * Résultat attendu : InvalidUriException est levée
     */
    public function testItRejectsUriExceedingMaximumLength(): void
    {
        $this->expectException(
            InvalidUriException::class,
        );

        new Uri(
            '/' . str_repeat('a', 2048),
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Uri valide
     */
    /**
     * But : Vérifier que fromExternal() ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 18 inputs hostiles variés
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
            ['uri'],
            new stdClass(),
            $resource,
            str_repeat('/test', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '../../../../../etc/passwd',
            'https://example.com/orders?id=42',
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertInstanceOf(
                Uri::class,
                $uri,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne toujours une Uri valide.
     *
     * Entrée : 12 inputs variés
     * Résultat attendu : Valeur non vide commençant par '/'
     */
    public function testFromExternalAlwaysReturnsValidUri(): void
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
            str_repeat('/test', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertNotSame(
                '',
                $uri->value(),
            );

            self::assertTrue(
                str_starts_with(
                    $uri->value(),
                    '/',
                ),
            );

            self::assertLessThanOrEqual(
                2048,
                mb_strlen($uri->value()),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour les payloads hostiles.
     *
     * Entrée : 8 inputs hostiles (injections, path traversal, etc.)
     * Résultat attendu : '/' pour chaque input
     */
    public function testItFallsBackToRootForHostilePayloads(): void
    {
        $inputs = [
            null,
            [],
            new stdClass(),
            str_repeat('a', 500000),
        ];

        foreach ($inputs as $input) {
            $uri = Uri::fromExternal($input);

            self::assertSame(
                '/',
                $uri->value(),
            );
        }
    }

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec une URI de 5 000 caractères.
     *
     * Entrée : '/' . str_repeat('segment/', 5000)
     * Résultat attendu : '/' retourné, aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = '/' . str_repeat('a', 1000000);

        $uri = Uri::fromExternal($payload);

        self::assertSame(
            '/',
            $uri->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte un payload binaire sans crash.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Uri créée ou fallback '/', aucune exception
     */
    public function testItHandlesBinaryPayload(): void
    {
        $uri = Uri::fromExternal(
            "/test\x00\x01\x02",
        );

        self::assertInstanceOf(
            Uri::class,
            $uri,
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte de l'UTF-8 invalide sans crash.
     *
     * Entrée : hex2bin('b131')
     * Résultat attendu : Uri créée ou fallback '/', aucune exception
     */
    public function testItHandlesInvalidUtf8Payload(): void
    {
        $uri = Uri::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            Uri::class,
            $uri,
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidUris(): iterable
    {
        yield 'root' => [
            '/',
            '/',
        ];

        yield 'simple path' => [
            '/orders',
            '/orders',
        ];

        yield 'nested path' => [
            '/orders/create',
            '/orders/create',
        ];

        yield 'trimmed path' => [
            '   /orders/create   ',
            '/orders/create',
        ];

        yield 'duplicate slashes' => [
            '///orders///create///',
            '/orders/create',
        ];

        yield 'full url' => [
            'https://example.com/orders?id=42',
            '/orders',
        ];

        yield 'without leading slash' => [
            'orders',
            '/orders',
        ];

        yield 'query string removed' => [
            '/orders?id=42',
            '/orders',
        ];

        yield 'fragment removed' => [
            '/orders#create',
            '/orders',
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidUris(): iterable
    {
        yield 'too long' => [
            '/' . str_repeat('a', 3000),
        ];
    }
}