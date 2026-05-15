<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Constantes\PersistenceLimits;
use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistenceResult.
 *
 * Objectifs :
 * - robustesse
 * - stabilité
 * - protection mémoire
 * - payloads hostiles
 * - overflow
 * - corruption données
 *
 * IMPORTANT :
 * Aucun accès DB ici.
 *
 * Les crash tests doivent rester :
 * - rapides
 * - déterministes
 * - isolés
 */
final class PersistenceResultCrashTest extends TestCase
{
    /**
     * But : Vérifier qu'un persistedCount négatif lève une InvalidArgumentException.
     *
     * Entrée : new PersistenceResult(persistedCount:-1, failedCount:0)
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function testItRejectsNegativePersistedCount(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        new PersistenceResult(
            persistedCount: -1,
            failedCount: 0,
        );
    }

    /**
     * But : Vérifier qu'un failedCount négatif lève une InvalidArgumentException.
     *
     * Entrée : new PersistenceResult(persistedCount:0, failedCount:-1)
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function testItRejectsNegativeFailedCount(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        new PersistenceResult(
            persistedCount: 0,
            failedCount: -1,
        );
    }

    /**
     * But : Vérifier que les types invalides dans les erreurs sont filtrés sauf les scalaires convertibles.
     *
     * Entrée : errors=[stdClass, resource, null, [], true, 123, 'valid-error']
     * Résultat attendu : getErrors() = ['1', '123', 'valid-error']
     */
    public function testItIgnoresInvalidErrorTypes(): void
    {
        $resource = fopen(
            'php://memory',
            'rb',
        );

        self::assertIsResource($resource);

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                new \stdClass(),
                $resource,
                null,
                [],
                true,
                123,
                'valid-error',
            ],
        );

        fclose($resource);

        self::assertSame(
            [
                '1',
                '123',
                'valid-error',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les chaînes vides/blancs sont supprimées de la liste d'erreurs.
     *
     * Entrée : errors=['', ' ', "\n", "\t", 'valid']
     * Résultat attendu : getErrors() = ['valid']
     */
    public function testItIgnoresEmptyErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                '',
                ' ',
                "\n",
                "\t",
                'valid',
            ],
        );

        self::assertSame(
            [
                'valid',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les erreurs trop longues sont tronquées à PersistenceLimits::MAX_ERROR_LENGTH.
     *
     * Entrée : errors=[str_repeat('A', 10000)]
     * Résultat attendu : mb_strlen(getErrors()[0]) = MAX_ERROR_LENGTH
     */
    public function testItLimitsErrorSize(): void
    {
        $huge = str_repeat(
            'A',
            10000,
        );

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $huge,
            ],
        );

        self::assertSame(
            PersistenceLimits::MAX_ERROR_LENGTH,
            mb_strlen(
                $result->getErrors()[0],
            ),
        );
    }

    /**
     * But : Vérifier que la collection d'erreurs est limitée à PersistenceLimits::MAX_ERRORS.
     *
     * Entrée : 10 000 erreurs distinctes
     * Résultat attendu : count(getErrors()) = MAX_ERRORS
     */
    public function testItLimitsErrorCollectionSize(): void
    {
        $errors = [];

        for ($i = 0; $i < 10000; ++$i) {
            $errors[] = 'error-' . $i;
        }

        $result = PersistenceResult::failure(
            failedCount: 10000,
            errors: $errors,
        );

        self::assertCount(
            PersistenceLimits::MAX_ERRORS,
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les payloads UTF-8 invalides dans les erreurs n'entraînent pas de crash.
     *
     * Entrée : errors=["\xB1\x31"]
     * Résultat attendu : count(getErrors()) = 1, aucun crash
     */
    public function testItHandlesUtf8HostilePayloads(): void
    {
        $payload = "\xB1\x31";

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $payload,
            ],
        );

        self::assertCount(
            1,
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que 5 000 fusions successives de PersistenceResult ne causent pas de crash mémoire.
     *
     * Entrée : 5 000 merge() avec un résultat partiel différent à chaque itération
     * Résultat attendu : persistedCount=5000, failedCount=5000, count(errors)=MAX_ERRORS
     */
    public function testItHandlesMassiveMergeWithoutCrash(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 0,
        );

        for ($i = 0; $i < 5000; ++$i) {
            $result = $result->merge(
                PersistenceResult::partial(
                    persistedCount: 1,
                    failedCount: 1,
                    errors: [
                        'error-' . $i,
                    ],
                ),
            );
        }

        self::assertSame(
            5000,
            $result->getPersistedCount(),
        );

        self::assertSame(
            5000,
            $result->getFailedCount(),
        );

        /**
         * Protection mémoire active.
         */
        self::assertCount(
            PersistenceLimits::MAX_ERRORS,
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que PHP_INT_MAX comme compteur ne provoque pas d'erreur.
     *
     * Entrée : persistedCount=PHP_INT_MAX, failedCount=PHP_INT_MAX
     * Résultat attendu : getTotalCount() = PHP_INT_MAX (protection overflow)
     */
    public function testItHandlesIntegerOverflow(): void
    {
        $result = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertSame(
            PHP_INT_MAX,
            $result->getTotalCount(),
        );
    }

    /**
     * But : Vérifier que la fusion de deux compteurs PHP_INT_MAX est protégée contre l'overflow.
     *
     * Entrée : Deux PersistenceResult avec persistedCount=PHP_INT_MAX
     * Résultat attendu : getPersistedCount() = PHP_INT_MAX
     */
    public function testItHandlesMergeIntegerOverflow(): void
    {
        $first = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: 0,
        );

        $second = new PersistenceResult(
            persistedCount: PHP_INT_MAX,
            failedCount: 0,
        );

        $merged = $first->merge($second);

        self::assertSame(
            PHP_INT_MAX,
            $merged->getPersistedCount(),
        );
    }

    /**
     * But : Vérifier que la fusion de deux résultats vides produit un résultat vide sans erreur.
     *
     * Entrée : Deux PersistenceResult(0, 0)
     * Résultat attendu : getTotalCount()=0, hasErrors()=false
     */
    public function testItHandlesEmptyMerge(): void
    {
        $first = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        $second = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        $merged = $first->merge($second);

        self::assertSame(
            0,
            $merged->getTotalCount(),
        );

        self::assertFalse(
            $merged->hasErrors(),
        );
    }

    /**
     * But : Vérifier que getSuccessRate() ne retourne jamais NaN même avec zéro logs.
     *
     * Entrée : new PersistenceResult(persistedCount:0, failedCount:0)
     * Résultat attendu : is_nan(getSuccessRate()) = false
     */
    public function testItHandlesNanSuccessRateProtection(): void
    {
        $result = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        self::assertFalse(
            is_nan(
                $result->getSuccessRate(),
            ),
        );
    }

    /**
     * But : Vérifier que 1 000 erreurs identiques sont dédupliquées en une seule entrée.
     *
     * Entrée : 1 000 fois 'duplicate' dans errors
     * Résultat attendu : getErrors() = ['duplicate']
     */
    public function testItHandlesLargeErrorDeduplication(): void
    {
        $errors = [];

        for ($i = 0; $i < 1000; ++$i) {
            $errors[] = 'duplicate';
        }

        $result = PersistenceResult::failure(
            failedCount: 1000,
            errors: $errors,
        );

        self::assertSame(
            [
                'duplicate',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les espaces autour des messages d'erreur sont supprimés.
     *
     * Entrée : errors=['   hello   ']
     * Résultat attendu : getErrors() = ['hello']
     */
    public function testItTrimsWhitespaceAroundErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                '   hello   ',
            ],
        );

        self::assertSame(
            [
                'hello',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les erreurs avec des emojis de grande taille sont tronquées.
     *
     * Entrée : errors=[str_repeat('🔥', 10000)]
     * Résultat attendu : mb_strlen(getErrors()[0]) <= MAX_ERROR_LENGTH
     */
    public function testItHandlesHugeUnicodeErrors(): void
    {
        $payload = str_repeat(
            '🔥',
            10000,
        );

        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                $payload,
            ],
        );

        self::assertLessThanOrEqual(
            PersistenceLimits::MAX_ERROR_LENGTH,
            mb_strlen(
                $result->getErrors()[0],
            ),
        );
    }

    /**
     * But : Vérifier que modifier le tableau retourné par getErrors() ne mute pas l'état interne.
     *
     * Entrée : errors=['error'], ajout externe 'modified' au tableau retourné
     * Résultat attendu : getErrors() retourne toujours ['error']
     */
    public function testItReturnsImmutableErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                'error',
            ],
        );

        $errors = $result->getErrors();

        $errors[] = 'modified';

        self::assertSame(
            [
                'error',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que 50 000 erreurs volumineuses sont limitées sans explosion mémoire.
     *
     * Entrée : 50 000 erreurs de 1 000 caractères chacune (toutes identiques)
     * Résultat attendu : count(getErrors()) = 1 (dédupliqué)
     */
    public function testItHandlesMassiveErrorPayloadWithoutMemoryExplosion(): void
    {
        $errors = [];

        for ($i = 0; $i < 50000; ++$i) {
            $errors[] = str_repeat(
                'x',
                1000,
            );
        }

        $result = PersistenceResult::failure(
            failedCount: 50000,
            errors: $errors,
        );

        self::assertCount(
            1,
            $result->getErrors(),
        );
    }
}