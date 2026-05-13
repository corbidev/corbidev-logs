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

    public function testItGeneratesDifferentValues(): void
    {
        $a = RequestId::generate();
        $b = RequestId::generate();

        self::assertNotSame(
            $a->value(),
            $b->value(),
        );
    }

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

    public function testItRejectsEmptyString(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('');
    }

    public function testItRejectsWhitespaceOnly(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('     ');
    }

    public function testItRejectsTooShortValue(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId('ab');
    }

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

    public function testItRejectsSpaces(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req test',
        );
    }

    public function testItRejectsSlash(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req/test',
        );
    }

    public function testItRejectsBackslash(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req\test',
        );
    }

    public function testItRejectsHtmlInjection(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '<script>alert(1)</script>',
        );
    }

    public function testItRejectsSqlInjectionPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "' OR 1=1 --",
        );
    }

    public function testItRejectsUnicodeCharacters(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'réq_test',
        );
    }

    public function testItRejectsEmoji(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            'req_🔥',
        );
    }

    public function testItRejectsControlCharacters(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\n_test",
        );
    }

    public function testItRejectsTabulation(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\t_test",
        );
    }

    public function testItRejectsNullByte(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            "req_\0_test",
        );
    }

    public function testItRejectsJsonPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '{"id":"test"}',
        );
    }

    public function testItRejectsArrayLikePayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '[]',
        );
    }

    public function testItRejectsUrlEncodedPayload(): void
    {
        $this->expectException(
            InvalidRequestIdException::class,
        );

        new RequestId(
            '%3Cscript%3E',
        );
    }

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