<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidRequestException;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject Request.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les normalisations HTTP
 * - garantir les fallbacks ingestion
 * - garantir la robustesse face aux payloads hostiles
 */
final class RequestTest extends TestCase
{
    /**
     * But : Vérifier que Request est correctement créé avec des paramètres valides.
     *
     * Entrée : uri='/orders', method='POST', userAgent='Mozilla/5.0'
     * Résultat attendu : Les accesseurs uri(), method(), userAgent() retournent les valeurs normalisées
     */
    public function testItCreatesValidRequest(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'POST',
            'Mozilla/5.0',
        );

        self::assertSame(
            '/orders',
            $request->uri()->value(),
        );

        self::assertSame(
            'POST',
            $request->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    /**
     * But : Vérifier que la méthode HTTP est normalisée en majuscules avec trim.
     *
     * Entrée : ' post '
     * Résultat attendu : method() = 'POST'
     */
    public function testItNormalizesMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            ' post ',
        );

        self::assertSame(
            'POST',
            $request->method(),
        );
    }

    /**
     * But : Vérifier que le user agent est normalisé (trim des espaces).
     *
     * Entrée : '  Mozilla/5.0  '
     * Résultat attendu : userAgent() = 'Mozilla/5.0'
     */
    public function testItNormalizesUserAgent(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'GET',
            '  Mozilla/5.0  ',
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    /**
     * But : Vérifier que Request rejette une méthode HTTP invalide.
     *
     * Entrée : 'INVALID_METHOD'
     * Résultat attendu : InvalidRequestException est levée
     */
    public function testItRejectsInvalidMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            'INVALID',
        );
    }

    /**
     * But : Vérifier que Request rejette une méthode HTTP vide.
     *
     * Entrée : method = ''
     * Résultat attendu : InvalidRequestException est levée
     */
    public function testItRejectsEmptyMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            '',
        );
    }

    /**
     * But : Vérifier que Request rejette une méthode HTTP trop longue.
     *
     * Entrée : method = str_repeat('A', 20)
     * Résultat attendu : InvalidRequestException est levée
     */
    public function testItRejectsTooLongMethod(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            str_repeat('A', 20),
        );
    }

    /**
     * But : Vérifier que Request rejette un user agent dépassant 500 caractères.
     *
     * Entrée : userAgent = str_repeat('A', 501)
     * Résultat attendu : InvalidRequestException est levée
     */
    public function testItRejectsTooLongUserAgent(): void
    {
        $this->expectException(
            InvalidRequestException::class,
        );

        new Request(
            new Uri('/orders'),
            'GET',
            str_repeat('A', 501),
        );
    }

    /**
     * But : Vérifier que fromExternal() crée une Request valide depuis des paramètres externes.
     *
     * Entrée : uri='/orders', method='post', userAgent='Mozilla/5.0'
     * Résultat attendu : Request avec uri='/orders', method='POST', userAgent='Mozilla/5.0'
     */
    public function testItCreatesFromExternal(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'post',
            'Mozilla/5.0',
        );

        self::assertSame(
            '/orders',
            $request->uri()->value(),
        );

        self::assertSame(
            'POST',
            $request->method(),
        );

        self::assertSame(
            'Mozilla/5.0',
            $request->userAgent(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 'GET' et '/' pour une méthode invalide.
     *
     * Entrée : method='INVALID'
     * Résultat attendu : method='GET', uri='/'
     */
    public function testItFallsBackForInvalidMethod(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'INVALID',
        );

        self::assertSame(
            'GET',
            $request->method(),
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '/' pour une URI trop longue.
     *
     * Entrée : uri = str_repeat('/', 5000)
     * Résultat attendu : uri = '/'
     */
    public function testItFallsBackForInvalidUri(): void
    {
        $request = Request::fromExternal(
            str_repeat('/', 5000),
            'POST',
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne '' pour un user agent trop long.
     *
     * Entrée : userAgent = str_repeat('A', 5000)
     * Résultat attendu : userAgent = ''
     */
    public function testItFallsBackForInvalidUserAgent(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'GET',
            str_repeat('A', 5000),
        );

        self::assertSame(
            '',
            $request->userAgent(),
        );
    }

    /**
     * But : Vérifier que fromExternal() gère correctement des types invalides.
     *
     * Entrée : uri=[], method=stdClass, userAgent=resource
     * Résultat attendu : uri='/', method='GET', userAgent=''
     */
    public function testItFallsBackForInvalidTypes(): void
    {
        $request = Request::fromExternal(
            [],
            new stdClass(),
            fopen('php://memory', 'r'),
        );

        self::assertSame(
            '/',
            $request->uri()->value(),
        );

        self::assertSame(
            'GET',
            $request->method(),
        );

        self::assertSame(
            '',
            $request->userAgent(),
        );
    }

    /**
     * But : Vérifier que isGet() retourne true et isPost() false pour GET.
     *
     * Entrée : method='GET'
     * Résultat attendu : isGet() = true, isPost() = false
     */
    public function testItDetectsGetMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'GET',
        );

        self::assertTrue(
            $request->isGet(),
        );

        self::assertFalse(
            $request->isPost(),
        );
    }

    /**
     * But : Vérifier que isPost() retourne true et isGet() false pour POST.
     *
     * Entrée : method='POST'
     * Résultat attendu : isPost() = true, isGet() = false
     */
    public function testItDetectsPostMethod(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'POST',
        );

        self::assertTrue(
            $request->isPost(),
        );

        self::assertFalse(
            $request->isGet(),
        );
    }

    /**
     * But : Vérifier que equals() compare correctement deux Request.
     *
     * Entrée : Deux Request identiques, puis deux Request différents
     * Résultat attendu : equals() = true / false
     */
    public function testItComparesRequests(): void
    {
        $left = new Request(
            new Uri('/orders'),
            'POST',
            'UA',
        );

        $right = new Request(
            new Uri('/orders'),
            'POST',
            'UA',
        );

        $other = new Request(
            new Uri('/users'),
            'GET',
            'UA',
        );

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne le format 'METHOD URI'.
     *
     * Entrée : method='POST', uri='/orders'
     * Résultat attendu : (string) Request = 'POST /orders'
     */
    public function testItReturnsStableStringRepresentation(): void
    {
        $request = new Request(
            new Uri('/orders'),
            'POST',
        );

        self::assertSame(
            'POST /orders',
            (string) $request,
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un Request valide
     */
    /**
     * But : Vérifier que fromExternal() ne lève jamais d'exception avec des inputs hostiles.
     *
     * Entrée : 17 inputs hostiles variés pour uri, method et userAgent
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
            123,
            999999,
            [],
            ['request'],
            new stdClass(),
            $resource,
            str_repeat('A', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            '🔥🔥🔥',
        ];

        foreach ($inputs as $input) {
            $request = Request::fromExternal(
                $input,
                $input,
                $input,
            );

            self::assertInstanceOf(
                Request::class,
                $request,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne toujours une Request valide.
     *
     * Entrée : '/orders', 'POST', 'Mozilla/5.0'
     * Résultat attendu : Request valide avec les valeurs normalisées
     */
    public function testFromExternalAlwaysReturnsValidRequest(): void
    {
        $request = Request::fromExternal(
            '/orders',
            'POST',
            'Mozilla',
        );

        self::assertNotSame(
            '',
            $request->method(),
        );

        self::assertTrue(
            str_starts_with(
                $request->uri()->value(),
                '/',
            ),
        );
    }

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec des payloads de 1 000 000 caractères.
     *
     * Entrée : uri, method, userAgent = 1M caractères chacun
     * Résultat attendu : Instance Request créée ou fallback, aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat(
            'A',
            1000000,
        );

        $request = Request::fromExternal(
            $payload,
            $payload,
            $payload,
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte des octets binaires sans crash.
     *
     * Entrée : "\x00\x01\x02" pour uri, method, userAgent
     * Résultat attendu : Instance Request créée ou fallback, aucune exception
     */
    public function testItHandlesBinaryPayload(): void
    {
        $request = Request::fromExternal(
            "\x00\x01\x02",
            "\x00\x01\x02",
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte de l'UTF-8 invalide sans crash.
     *
     * Entrée : hex2bin('b131') pour uri, method, userAgent
     * Résultat attendu : Instance Request créée ou fallback, aucune exception
     */
    public function testItHandlesInvalidUtf8Payload(): void
    {
        $payload = hex2bin('b131');

        $request = Request::fromExternal(
            $payload,
            $payload,
            $payload,
        );

        self::assertInstanceOf(
            Request::class,
            $request,
        );
    }

    public function testItSupportsAllAllowedMethods(): void
    {
        $methods = [
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS',
        ];

        foreach ($methods as $method) {
            $request = new Request(
                new Uri('/orders'),
                $method,
            );

            self::assertSame(
                $method,
                $request->method(),
            );
        }
    }
}