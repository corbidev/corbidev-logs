<?php

declare(strict_types=1);

namespace App\Tests\Unit\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidRequestIdException;
use App\Log\Domain\ValueObject\RequestId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires et crash tests de RequestId.
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - stabilité
 * - prédictibilité
 * - sécurité
 * - immutabilité
 *
 * GARANTIES TESTÉES :
 * -------------------
 * - normalisation
 * - validation
 * - génération automatique
 * - limites
 * - caractères invalides
 * - stabilité equals()
 * - stabilité __toString()
 * - résistance aux payloads hostiles
 * - résistance unicode
 * - résistance injections
 */
#[CoversClass(RequestId::class)]
final class RequestIdTest extends TestCase
{
    /**
     * But : Vérifier que RequestId est créé correctement depuis une valeur valide.
     *
     * Entrée : 'req_8f5c1a'
     * Résultat attendu : value() = 'req_8f5c1a'
     */
    public function testItCreatesValidRequestId(): void
    {
        $requestId = new RequestId(
            'req_8f5c1a',
        );

        self::assertSame(
            'req_8f5c1a',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId est normalisé en minuscules avec trim.
     *
     * Entrée : '   REQ_ABC_123   '
     * Résultat attendu : 'req_abc_123'
     */
    public function testItNormalizesRequestId(): void
    {
        $requestId = new RequestId(
            '   REQ_ABC_123   ',
        );

        self::assertSame(
            'req_abc_123',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que la conversion en string retourne la valeur du RequestId.
     *
     * Entrée : new RequestId('req_test')
     * Résultat attendu : (string) RequestId = 'req_test'
     */
    public function testItConvertsToString(): void
    {
        $requestId = new RequestId(
            'req_test',
        );

        self::assertSame(
            'req_test',
            (string) $requestId,
        );
    }

    /**
     * But : Vérifier que equals() retourne true pour des RequestId identiques après normalisation.
     *
     * Entrée : 'req_same' vs 'REQ_SAME' (normalisés identiques)
     * Résultat attendu : equals() = true
     */
    public function testItComparesEquals(): void
    {
        $a = new RequestId(
            'req_same',
        );

        $b = new RequestId(
            'REQ_SAME',
        );

        self::assertTrue(
            $a->equals($b),
        );
    }

    /**
     * But : Vérifier que equals() retourne false pour des RequestId différents.
     *
     * Entrée : 'req_a' vs 'req_b'
     * Résultat attendu : equals() = false
     */
    public function testItDetectsDifferentRequestIds(): void
    {
        $a = new RequestId(
            'req_a',
        );

        $b = new RequestId(
            'req_b',
        );

        self::assertFalse(
            $a->equals($b),
        );
    }

    /**
     * But : Vérifier que generate() produit un RequestId avec préfixe 'req_' et longueur ≥ 10.
     *
     * Entrée : Appel à RequestId::generate()
     * Résultat attendu : Commence par 'req_', longueur ≥ 10
     */
    public function testItGeneratesRequestId(): void
    {
        $requestId = RequestId::generate();

        self::assertStringStartsWith(
            'req_',
            $requestId->value(),
        );

        self::assertGreaterThanOrEqual(
            10,
            mb_strlen(
                $requestId->value(),
            ),
        );
    }

    /**
     * But : Vérifier que deux appels à generate() produisent des valeurs différentes.
     *
     * Entrée : Deux appels successifs à RequestId::generate()
     * Résultat attendu : Les deux valeurs sont distinctes
     */
    public function testItGeneratesDifferentValues(): void
    {
        $a = RequestId::generate();
        $b = RequestId::generate();

        self::assertNotSame(
            $a->value(),
            $b->value(),
        );
    }

    /**
     * But : Vérifier que fromNullable() génère un RequestId si null est passé.
     *
     * Entrée : null
     * Résultat attendu : RequestId valide commençant par 'req_'
     */
    public function testFromNullableGeneratesValueWhenNull(): void
    {
        $requestId = RequestId::fromNullable(
            null,
        );

        self::assertStringStartsWith(
            'req_',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que fromNullable() génère un RequestId si une chaîne vide est passée.
     *
     * Entrée : ''
     * Résultat attendu : RequestId valide commençant par 'req_'
     */
    public function testFromNullableGeneratesValueWhenEmpty(): void
    {
        $requestId = RequestId::fromNullable(
            '',
        );

        self::assertStringStartsWith(
            'req_',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que fromNullable() génère un RequestId si seuls des espaces sont passés.
     *
     * Entrée : '   '
     * Résultat attendu : RequestId valide commençant par 'req_'
     */
    public function testFromNullableGeneratesValueWhenWhitespace(): void
    {
        $requestId = RequestId::fromNullable(
            '     ',
        );

        self::assertStringStartsWith(
            'req_',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId accepte la longueur minimale (3 caractères).
     *
     * Entrée : 'abc'
     * Résultat attendu : RequestId créé sans exception
     */
    public function testItAcceptsMinimumLength(): void
    {
        $requestId = new RequestId(
            'abc',
        );

        self::assertSame(
            'abc',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId accepte la longueur maximale (100 caractères).
     *
     * Entrée : str_repeat('a', 100)
     * Résultat attendu : RequestId créé sans exception
     */
    public function testItAcceptsMaximumLength(): void
    {
        $value = str_repeat(
            'a',
            100,
        );

        $requestId = new RequestId(
            $value,
        );

        self::assertSame(
            $value,
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId rejette une chaîne vide.
     *
     * Entrée : ''
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsEmptyString(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('');
    }

    /**
     * But : Vérifier que RequestId rejette une chaîne composée uniquement d'espaces.
     *
     * Entrée : '   '
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsWhitespaceOnly(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('     ');
    }

    /**
     * But : Vérifier que RequestId rejette une valeur de moins de 3 caractères.
     *
     * Entrée : 'ab'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsTooShortValue(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('ab');
    }

    /**
     * But : Vérifier que RequestId rejette une valeur de plus de 100 caractères.
     *
     * Entrée : str_repeat('a', 101)
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsTooLongValue(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            str_repeat(
                'a',
                101,
            ),
        );
    }

    /**
     * But : Vérifier que RequestId rejette une valeur contenant des espaces.
     *
     * Entrée : 'req test'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsSpaces(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req test',
        );
    }

    /**
     * But : Vérifier que RequestId rejette une valeur contenant un slash.
     *
     * Entrée : 'req/test'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsSlash(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req/test',
        );
    }

    /**
     * But : Vérifier que RequestId rejette une valeur contenant un backslash.
     *
     * Entrée : 'req\test'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsBackslash(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req\test',
        );
    }

    /**
     * But : Vérifier que RequestId rejette une injection HTML.
     *
     * Entrée : '<script>alert(1)</script>'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsHtmlInjection(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '<script>alert(1)</script>',
        );
    }

    /**
     * But : Vérifier que RequestId rejette une injection SQL.
     *
     * Entrée : "' OR 1=1 --"
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsSqlInjectionPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "' OR 1=1 --",
        );
    }

    /**
     * But : Vérifier que RequestId rejette les caractères Unicode non-ASCII.
     *
     * Entrée : 'réq_test'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsUnicodeCharacters(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'réq_test',
        );
    }

    /**
     * But : Vérifier que RequestId rejette les emoji.
     *
     * Entrée : 'req_🔥'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsEmoji(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req_🔥',
        );
    }

    /**
     * But : Vérifier que RequestId rejette les caractères de contrôle (newline).
     *
     * Entrée : "req_\n_test"
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsControlCharacters(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\n_test",
        );
    }

    /**
     * But : Vérifier que RequestId rejette les tabulations.
     *
     * Entrée : "req_\t_test"
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsTabulation(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\t_test",
        );
    }

    /**
     * But : Vérifier que RequestId rejette les octets nuls.
     *
     * Entrée : "req_\0_test"
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsNullByte(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\0_test",
        );
    }

    /**
     * But : Vérifier que RequestId rejette un payload JSON.
     *
     * Entrée : '{"id":"test"}'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsJsonPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '{"id":"test"}',
        );
    }

    /**
     * But : Vérifier que RequestId rejette un payload ressemblant à un tableau.
     *
     * Entrée : '[]'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsArrayLikePayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '[]',
        );
    }

    /**
     * But : Vérifier que RequestId rejette un payload encodé en URL.
     *
     * Entrée : '%3Cscript%3E'
     * Résultat attendu : InvalidRequestIdException est levée
     */
    public function testItRejectsUrlEncodedPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '%3Cscript%3E',
        );
    }

    /**
     * But : Vérifier que RequestId accepte le caractère tiret.
     *
     * Entrée : 'req-api-prod'
     * Résultat attendu : value() = 'req-api-prod'
     */
    public function testItAcceptsDashCharacter(): void
    {
        $requestId = new RequestId(
            'req-api-prod',
        );

        self::assertSame(
            'req-api-prod',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId accepte le caractère underscore.
     *
     * Entrée : 'req_api_prod'
     * Résultat attendu : value() = 'req_api_prod'
     */
    public function testItAcceptsUnderscoreCharacter(): void
    {
        $requestId = new RequestId(
            'req_api_prod',
        );

        self::assertSame(
            'req_api_prod',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que RequestId accepte les caractères numériques.
     *
     * Entrée : 'req_123456'
     * Résultat attendu : value() = 'req_123456'
     */
    public function testItAcceptsNumericCharacters(): void
    {
        $requestId = new RequestId(
            'req_123456',
        );

        self::assertSame(
            'req_123456',
            $requestId->value(),
        );
    }

    /**
     * But : Vérifier que equals() utilise les valeurs normalisées pour la comparaison.
     *
     * Entrée : 'REQ_TEST' vs 'req_test'
     * Résultat attendu : equals() = true
     */
    public function testEqualsUsesNormalizedValues(): void
    {
        $a = new RequestId(
            'REQ_TEST',
        );

        $b = new RequestId(
            'req_test',
        );

        self::assertTrue(
            $a->equals($b),
        );
    }

    /**
     * But : Vérifier que 100 RequestId générés sont tous valides (format /^req_[a-z0-9]+$/).
     *
     * Entrée : 100 appels à RequestId::generate()
     * Résultat attendu : Chaque valeur correspond au pattern /^req_[a-z0-9]+$/
     */
    public function testGeneratedRequestIdIsAlwaysValid(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $requestId = RequestId::generate();

            self::assertMatchesRegularExpression(
                '/^req_[a-z0-9]+$/',
                $requestId->value(),
            );
        }
    }
}