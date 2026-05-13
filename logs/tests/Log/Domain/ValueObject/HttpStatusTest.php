<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidHttpStatusException;
use App\Log\Domain\ValueObject\HttpStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * Tests unitaires du ValueObject HttpStatus.
 *
 * Objectifs :
 * - garantir les invariants métier
 * - garantir les bornes HTTP
 * - verrouiller les comportements de normalisation
 * - garantir la stabilité des helpers
 * - garantir la robustesse ingestion
 */
final class HttpStatusTest extends TestCase
{
    #[DataProvider('provideValidStatuses')]
    /**
     * But : Vérifier que HttpStatus accepte les codes HTTP valides.
     *
     * Entrée : Cas fournis par le DataProvider `provideValidStatuses()`
     * Résultat attendu : HttpStatus créé sans exception
     */
    public function testItCreatesValidHttpStatus(
        int $value,
    ): void {
        $status = new HttpStatus($value);

        self::assertSame(
            $value,
            $status->value(),
        );
    }

    #[DataProvider('provideInvalidStatuses')]
    /**
     * But : Vérifier que HttpStatus rejette les codes HTTP invalides.
     *
     * Entrée : Cas fournis par le DataProvider `provideInvalidStatuses()`
     * Résultat attendu : InvalidHttpStatusException est levée
     */
    public function testItRejectsInvalidHttpStatus(
        int $value,
    ): void {
        $this->expectException(
            InvalidHttpStatusException::class,
        );

        new HttpStatus($value);
    }

    /**
     * But : Vérifier que fromExternal() accepte un entier valide.
     *
     * Entrée : 404
     * Résultat attendu : HttpStatus avec valeur 404
     */
    public function testItCreatesFromExternalInteger(): void
    {
        $status = HttpStatus::fromExternal(404);

        self::assertSame(
            404,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte une string numérique valide.
     *
     * Entrée : '500'
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItCreatesFromExternalNumericString(): void
    {
        $status = HttpStatus::fromExternal('500');

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 500 pour une string invalide.
     *
     * Entrée : 'invalid-status'
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItFallsBackTo500ForInvalidString(): void
    {
        $status = HttpStatus::fromExternal(
            'invalid-status',
        );

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 500 pour null.
     *
     * Entrée : null
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItFallsBackTo500ForNull(): void
    {
        $status = HttpStatus::fromExternal(null);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 500 pour un status trop petit (< 100).
     *
     * Entrée : 99
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItFallsBackTo500ForTooSmallStatus(): void
    {
        $status = HttpStatus::fromExternal(99);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() retourne 500 pour un status trop grand (> 599).
     *
     * Entrée : 600
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItFallsBackTo500ForTooLargeStatus(): void
    {
        $status = HttpStatus::fromExternal(600);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que isInformational() retourne true pour les codes 1xx.
     *
     * Entrée : HttpStatus(102)
     * Résultat attendu : isInformational() = true
     */
    public function testItDetectsInformationalStatus(): void
    {
        $status = new HttpStatus(102);

        self::assertTrue(
            $status->isInformational(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    /**
     * But : Vérifier que isSuccess() retourne true pour les codes 2xx.
     *
     * Entrée : HttpStatus(200)
     * Résultat attendu : isSuccess() = true
     */
    public function testItDetectsSuccessStatus(): void
    {
        $status = new HttpStatus(200);

        self::assertTrue(
            $status->isSuccess(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    /**
     * But : Vérifier que isRedirection() retourne true pour les codes 3xx.
     *
     * Entrée : HttpStatus(302)
     * Résultat attendu : isRedirection() = true
     */
    public function testItDetectsRedirectionStatus(): void
    {
        $status = new HttpStatus(302);

        self::assertTrue(
            $status->isRedirection(),
        );

        self::assertFalse(
            $status->isError(),
        );
    }

    /**
     * But : Vérifier que isClientError() et isError() retournent true pour les codes 4xx.
     *
     * Entrée : HttpStatus(404)
     * Résultat attendu : isClientError() = true, isError() = true
     */
    public function testItDetectsClientErrorStatus(): void
    {
        $status = new HttpStatus(404);

        self::assertTrue(
            $status->isClientError(),
        );

        self::assertTrue(
            $status->isError(),
        );
    }

    /**
     * But : Vérifier que isServerError() et isError() retournent true pour les codes 5xx.
     *
     * Entrée : HttpStatus(500)
     * Résultat attendu : isServerError() = true, isError() = true
     */
    public function testItDetectsServerErrorStatus(): void
    {
        $status = new HttpStatus(500);

        self::assertTrue(
            $status->isServerError(),
        );

        self::assertTrue(
            $status->isError(),
        );
    }

    /**
     * But : Vérifier que getFamily() retourne la famille correcte du code HTTP.
     *
     * Entrée : 200, 404, 500
     * Résultat attendu : '2xx', '4xx', '5xx' respectivement
     */
    public function testItReturnsCorrectFamily(): void
    {
        self::assertSame(
            '2xx',
            (new HttpStatus(200))->family(),
        );

        self::assertSame(
            '4xx',
            (new HttpStatus(404))->family(),
        );

        self::assertSame(
            '5xx',
            (new HttpStatus(500))->family(),
        );
    }

    /**
     * But : Vérifier que equals() compare correctement deux HttpStatus.
     *
     * Entrée : HttpStatus(200) vs HttpStatus(200), puis HttpStatus(200) vs HttpStatus(404)
     * Résultat attendu : equals() = true / false
     */
    public function testItComparesTwoStatuses(): void
    {
        $left = new HttpStatus(404);
        $right = new HttpStatus(404);
        $other = new HttpStatus(500);

        self::assertTrue(
            $left->equals($right),
        );

        self::assertFalse(
            $left->equals($other),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne le code HTTP sous forme de string.
     *
     * Entrée : HttpStatus(404)
     * Résultat attendu : (string) HttpStatus = '404'
     */
    public function testItReturnsStableStringRepresentation(): void
    {
        $status = new HttpStatus(404);

        self::assertSame(
            '404',
            (string) $status,
        );
    }

    /**
     * Crash test critique ingestion.
     *
     * Garantie :
     * - aucun crash
     * - aucune exception
     * - toujours un HttpStatus valide
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
            'invalid',
            true,
            false,
            [],
            ['500'],
            new stdClass(),
            $resource,
            str_repeat('9', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
            '<script>alert(1)</script>',
            "'; DROP TABLE logs; --",
            PHP_INT_MAX,
            PHP_INT_MIN,
            INF,
            -INF,
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertInstanceOf(
                HttpStatus::class,
                $status,
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne toujours un code HTTP valide (100-599).
     *
     * Entrée : 12 inputs variés
     * Résultat attendu : Valeur entre 100 et 599 inclus
     */
    public function testFromExternalAlwaysReturnsValidHttpStatus(): void
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
            str_repeat('9', 100000),
            "\x00\x01\x02",
            hex2bin('b131'),
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertGreaterThanOrEqual(
                100,
                $status->value(),
            );

            self::assertLessThanOrEqual(
                599,
                $status->value(),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier que fromExternal() retourne 500 pour les payloads hostiles.
     *
     * Entrée : 7 inputs hostiles (injections, binaire, etc.)
     * Résultat attendu : HttpStatus avec valeur 500
     */
    public function testItFallsBackTo500ForHostilePayloads(): void
    {
        $inputs = [
            null,
            'invalid',
            [],
            new stdClass(),
            "\x00\x01",
            hex2bin('b131'),
            '<script>',
        ];

        foreach ($inputs as $input) {
            $status = HttpStatus::fromExternal($input);

            self::assertSame(
                500,
                $status->value(),
            );
        }
    }

    /**
     * But : Vérifier que fromExternal() ne crashe pas avec un payload de 1 000 000 caractères.
     *
     * Entrée : str_repeat('9', 1000000)
     * Résultat attendu : HttpStatus avec valeur 500, aucune exception
     */
    public function testItHandlesHugePayloadWithoutCrash(): void
    {
        $payload = str_repeat('9', 1000000);

        $status = HttpStatus::fromExternal($payload);

        self::assertSame(
            500,
            $status->value(),
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte un payload binaire sans crash.
     *
     * Entrée : "\x00\x01\x02"
     * Résultat attendu : Instance HttpStatus créée ou fallback, aucune exception
     */
    public function testItHandlesBinaryPayload(): void
    {
        $status = HttpStatus::fromExternal(
            "\x00\x01\x02",
        );

        self::assertInstanceOf(
            HttpStatus::class,
            $status,
        );
    }

    /**
     * But : Vérifier que fromExternal() accepte de l'UTF-8 invalide sans crash.
     *
     * Entrée : hex2bin('b131') (UTF-8 invalide)
     * Résultat attendu : Instance HttpStatus créée ou fallback, aucune exception
     */
    public function testItHandlesInvalidUtf8Payload(): void
    {
        $status = HttpStatus::fromExternal(
            hex2bin('b131'),
        );

        self::assertInstanceOf(
            HttpStatus::class,
            $status,
        );
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function provideValidStatuses(): iterable
    {
        yield 'minimum valid' => [100];

        yield 'success' => [200];

        yield 'redirection' => [302];

        yield 'client error' => [404];

        yield 'server error' => [500];

        yield 'maximum valid' => [599];
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function provideInvalidStatuses(): iterable
    {
        yield 'too small' => [99];

        yield 'negative' => [-1];

        yield 'zero' => [0];

        yield 'too large' => [600];

        yield 'very large' => [999];
    }
}