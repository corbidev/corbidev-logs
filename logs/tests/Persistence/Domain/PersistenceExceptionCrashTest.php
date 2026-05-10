<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Constantes\PersistenceLimits;
use App\Persistence\Domain\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistenceException.
 *
 * Responsabilités :
 * - tester les payloads hostiles
 * - garantir les bornes
 * - empêcher les explosions mémoire
 * - garantir la robustesse
 * - vérifier les nettoyages défensifs
 */
final class PersistenceExceptionCrashTest extends TestCase
{
    public function testContextIsTrimmedWhenTooLarge(): void
    {
        $context = [];

        for ($i = 0; $i < 1000; ++$i) {
            $context['key_' . $i] = 'value';
        }

        $exception = PersistenceException::queryExecutionFailed(
            context: $context,
        );

        self::assertCount(
            PersistenceLimits::MAX_EXCEPTION_CONTEXT_ITEMS,
            $exception->getContext(),
        );
    }

    public function testContextKeyIsTrimmed(): void
    {
        $longKey = str_repeat('k', 5000);

        $exception = PersistenceException::queryExecutionFailed(
            context: [
                $longKey => 'value',
            ],
        );

        $keys = array_keys(
            $exception->getContext(),
        );

        self::assertCount(
            1,
            $keys,
        );

        self::assertSame(
            PersistenceLimits::MAX_EXCEPTION_CONTEXT_KEY_LENGTH,
            mb_strlen($keys[0]),
        );
    }

    public function testContextValueIsTrimmed(): void
    {
        $longValue = str_repeat('a', 10000);

        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'payload' => $longValue,
            ],
        );

        self::assertSame(
            PersistenceLimits::MAX_EXCEPTION_CONTEXT_VALUE_LENGTH,
            mb_strlen(
                $exception->getContext()['payload'],
            ),
        );
    }

    public function testEmptyKeysAreIgnored(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                '' => 'invalid',
                '   ' => 'invalid',
                'valid' => 'ok',
            ],
        );

        self::assertSame(
            [
                'valid' => 'ok',
            ],
            $exception->getContext(),
        );
    }

    public function testArrayValuesAreIgnored(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'valid' => 'ok',
                'array' => [
                    'hostile',
                ],
            ],
        );

        self::assertSame(
            [
                'valid' => 'ok',
            ],
            $exception->getContext(),
        );
    }

    public function testObjectValuesAreIgnored(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'valid' => 'ok',
                'object' => new \stdClass(),
            ],
        );

        self::assertSame(
            [
                'valid' => 'ok',
            ],
            $exception->getContext(),
        );
    }

    public function testResourceValuesAreIgnored(): void
    {
        $resource = fopen(
            'php://memory',
            'rb',
        );

        self::assertIsResource($resource);

        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'valid' => 'ok',
                'resource' => $resource,
            ],
        );

        fclose($resource);

        self::assertSame(
            [
                'valid' => 'ok',
            ],
            $exception->getContext(),
        );
    }

    public function testBinaryLikeContentDoesNotCrash(): void
    {
        $binary = random_bytes(512);

        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'payload' => $binary,
            ],
        );

        self::assertArrayHasKey(
            'payload',
            $exception->getContext(),
        );
    }

    public function testHugeUnicodePayloadDoesNotCrash(): void
    {
        $payload = str_repeat('🔥', 10000);

        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'unicode' => $payload,
            ],
        );

        self::assertLessThanOrEqual(
            PersistenceLimits::MAX_EXCEPTION_CONTEXT_VALUE_LENGTH,
            mb_strlen(
                $exception->getContext()['unicode'],
            ),
        );
    }

    public function testScalarValuesAreAccepted(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'int' => 42,
                'float' => 3.14,
                'bool' => true,
                'null' => null,
            ],
        );

        self::assertSame(
            [
                'int' => 42,
                'float' => 3.14,
                'bool' => true,
                'null' => null,
            ],
            $exception->getContext(),
        );
    }

    public function testWhitespaceStringValuesAreTrimmed(): void
    {
        $exception = PersistenceException::queryExecutionFailed(
            context: [
                'message' => '   hello world   ',
            ],
        );

        self::assertSame(
            'hello world',
            $exception->getContext()['message'],
        );
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        $previous = new \RuntimeException(
            'root cause',
        );

        $exception = PersistenceException::transactionFailed(
            previous: $previous,
        );

        self::assertSame(
            $previous,
            $exception->getPrevious(),
        );
    }

    public function testHugeContextDoesNotExplodeMemory(): void
    {
        $context = [];

        for ($i = 0; $i < 50000; ++$i) {
            $context['key_' . $i] = str_repeat(
                'x',
                1000,
            );
        }

        $exception = PersistenceException::queryExecutionFailed(
            context: $context,
        );

        self::assertCount(
            PersistenceLimits::MAX_EXCEPTION_CONTEXT_ITEMS,
            $exception->getContext(),
        );
    }
}