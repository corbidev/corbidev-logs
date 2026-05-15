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
    /**
     * But : Vérifier que le contexte est limité à MAX_EXCEPTION_CONTEXT_ITEMS éléments.
     *
     * Entrée : context avec 1 000 clés
     * Résultat attendu : count(getContext()) = MAX_EXCEPTION_CONTEXT_ITEMS
     */
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

    /**
     * But : Vérifier que les clés de contexte trop longues sont tronquées.
     *
     * Entrée : Clé de 5 000 caractères
     * Résultat attendu : mb_strlen(clé) = MAX_EXCEPTION_CONTEXT_KEY_LENGTH
     */
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

    /**
     * But : Vérifier que les valeurs de contexte trop longues sont tronquées.
     *
     * Entrée : Valeur de 10 000 caractères
     * Résultat attendu : mb_strlen(valeur) = MAX_EXCEPTION_CONTEXT_VALUE_LENGTH
     */
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

    /**
     * But : Vérifier que les clés vides ou de blancs sont ignorées dans le contexte.
     *
     * Entrée : context=['' => 'invalid', '   ' => 'invalid', 'valid' => 'ok']
     * Résultat attendu : getContext() = ['valid' => 'ok']
     */
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

    /**
     * But : Vérifier que les valeurs tableau dans le contexte sont ignorées.
     *
     * Entrée : context=['valid' => 'ok', 'array' => ['hostile']]
     * Résultat attendu : getContext() = ['valid' => 'ok']
     */
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

    /**
     * But : Vérifier que les objets PHP dans le contexte sont ignorés.
     *
     * Entrée : context=['valid' => 'ok', 'object' => new \stdClass()]
     * Résultat attendu : getContext() = ['valid' => 'ok']
     */
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

    /**
     * But : Vérifier que les ressources PHP dans le contexte sont ignorées.
     *
     * Entrée : context=['valid' => 'ok', 'resource' => fopen(...)]
     * Résultat attendu : getContext() = ['valid' => 'ok']
     */
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

    /**
     * But : Vérifier que du contenu binaire dans le contexte ne provoque pas de crash.
     *
     * Entrée : context=['payload' => random_bytes(512)]
     * Résultat attendu : La clé 'payload' est présente dans getContext()
     */
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

    /**
     * But : Vérifier qu'un payload Unicode de grande taille est tronqué sans crash.
     *
     * Entrée : context=['unicode' => str_repeat('🔥', 10000)]
     * Résultat attendu : mb_strlen(valeur) ≤ MAX_EXCEPTION_CONTEXT_VALUE_LENGTH
     */
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

    /**
     * But : Vérifier que les valeurs scalaires (int, float, bool, null) sont acceptées dans le contexte.
     *
     * Entrée : context=['int' => 42, 'float' => 3.14, 'bool' => true, 'null' => null]
     * Résultat attendu : getContext() retourne les 4 paires telles quelles
     */
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

    /**
     * But : Vérifier que les valeurs string avec espaces superflus sont nettoyées dans le contexte.
     *
     * Entrée : context=['message' => '   hello world   ']
     * Résultat attendu : getContext()['message'] = 'hello world'
     */
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

    /**
     * But : Vérifier que l'exception précédente est transmise et récupérable.
     *
     * Entrée : previous=RuntimeException('root cause')
     * Résultat attendu : getPrevious() retourne la même instance
     */
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

    /**
     * But : Vérifier que 50 000 entrées de contexte de 1 000 chars ne causent pas d'explosion mémoire.
     *
     * Entrée : 50 000 clés avec valeurs de 1 000 caractères
     * Résultat attendu : count(getContext()) = MAX_EXCEPTION_CONTEXT_ITEMS
     */
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