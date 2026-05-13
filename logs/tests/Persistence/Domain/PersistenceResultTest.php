<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Domain;

use App\Persistence\Domain\PersistenceResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistenceResult.
 *
 * OBJECTIFS :
 * -----------
 * - stabilité
 * - robustesse
 * - invariants métier
 * - immutabilité
 * - comportement prédictible
 * - sécurité des données
 *
 * IMPORTANT :
 * ------------
 * Ces tests ne doivent jamais :
 *
 * - utiliser Symfony
 * - utiliser Doctrine
 * - utiliser SQLite
 * - utiliser DatabaseTestCase
 *
 * PersistenceResult est un pur objet Domain immutable.
 */
final class PersistenceResultTest extends TestCase
{
    /**
     * But : Vérifier que PersistenceResult::success() crée un résultat complet avec taux 100%.
     *
     * Entrée : persistedCount=15
     * Résultat attendu : isSuccess()=true, successRate=100.0, pas d'erreurs
     */
    public function testItCreatesSuccessResult(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 15,
        );

        self::assertSame(
            15,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );

        self::assertSame(
            15,
            $result->getTotalCount(),
        );

        self::assertSame(
            100.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->hasPersistedLogs(),
        );

        self::assertFalse(
            $result->hasFailures(),
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que PersistenceResult::failure() crée un résultat d'échec avec erreurs.
     *
     * Entrée : failedCount=8, errors=['sql error', 'deadlock']
     * Résultat attendu : isFailure()=true, successRate=0.0, hasErrors()=true
     */
    public function testItCreatesFailureResult(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 8,
            errors: [
                'sql error',
                'deadlock',
            ],
        );

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            8,
            $result->getFailedCount(),
        );

        self::assertSame(
            8,
            $result->getTotalCount(),
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );

        self::assertTrue(
            $result->hasFailures(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );

        self::assertSame(
            [
                'sql error',
                'deadlock',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que PersistenceResult::partial() crée un résultat mixte avec taux partiel.
     *
     * Entrée : persistedCount=7, failedCount=3, errors=['one failure']
     * Résultat attendu : isPartial()=true, successRate=70.0, totalCount=10
     */
    public function testItCreatesPartialResult(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 7,
            failedCount: 3,
            errors: [
                'one failure',
            ],
        );

        self::assertSame(
            7,
            $result->getPersistedCount(),
        );

        self::assertSame(
            3,
            $result->getFailedCount(),
        );

        self::assertSame(
            10,
            $result->getTotalCount(),
        );

        self::assertSame(
            70.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->hasPersistedLogs(),
        );

        self::assertTrue(
            $result->hasFailures(),
        );

        self::assertTrue(
            $result->hasErrors(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertTrue(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isNothingToPersist(),
        );
    }

    /**
     * But : Vérifier que PersistenceResult::nothingToPersist() crée un résultat neutre.
     *
     * Entrée : Aucune
     * Résultat attendu : isNothingToPersist()=true, tous les compteurs à 0
     */
    public function testItCreatesNothingToPersistResult(): void
    {
        $result = PersistenceResult::nothingToPersist();

        self::assertSame(
            0,
            $result->getPersistedCount(),
        );

        self::assertSame(
            0,
            $result->getFailedCount(),
        );

        self::assertSame(
            0,
            $result->getTotalCount(),
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->isNothingToPersist(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->hasPersistedLogs(),
        );

        self::assertFalse(
            $result->hasFailures(),
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que merge() additionne correctement deux PersistenceResult.
     *
     * Entrée : Deux résultats partiels (10+2 et 5+3 avec erreurs distinctes)
     * Résultat attendu : persistedCount=15, failedCount=5, errors fusionnés, isPartial()=true
     */
    public function testItMergesResults(): void
    {
        $first = PersistenceResult::partial(
            persistedCount: 10,
            failedCount: 2,
            errors: [
                'error-1',
            ],
        );

        $second = PersistenceResult::partial(
            persistedCount: 5,
            failedCount: 3,
            errors: [
                'error-2',
            ],
        );

        $merged = $first->merge($second);

        self::assertSame(
            15,
            $merged->getPersistedCount(),
        );

        self::assertSame(
            5,
            $merged->getFailedCount(),
        );

        self::assertSame(
            20,
            $merged->getTotalCount(),
        );

        self::assertSame(
            75.0,
            $merged->getSuccessRate(),
        );

        self::assertSame(
            [
                'error-1',
                'error-2',
            ],
            $merged->getErrors(),
        );

        self::assertTrue(
            $merged->isPartial(),
        );

        self::assertFalse(
            $merged->isNothingToPersist(),
        );
    }

    /**
     * But : Vérifier que getSuccessRate() retourne 0.0 quand persistedCount et failedCount sont à 0.
     *
     * Entrée : new PersistenceResult(persistedCount:0, failedCount:0)
     * Résultat attendu : getSuccessRate() = 0.0, isNothingToPersist() = true
     */
    public function testItReturnsZeroSuccessRateWhenEmpty(): void
    {
        $result = new PersistenceResult(
            persistedCount: 0,
            failedCount: 0,
        );

        self::assertSame(
            0.0,
            $result->getSuccessRate(),
        );

        self::assertTrue(
            $result->isNothingToPersist(),
        );
    }

    /**
     * But : Vérifier que toArray() exporte toutes les propriétés dans un tableau structuré.
     *
     * Entrée : PersistenceResult::partial(persistedCount:8, failedCount:2, errors:['timeout'])
     * Résultat attendu : Tableau avec 9 clés correspondant aux propriétés attendues
     */
    public function testItExportsArray(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 8,
            failedCount: 2,
            errors: [
                'timeout',
            ],
        );

        self::assertSame(
            [
                'persistedCount' => 8,
                'failedCount' => 2,
                'totalCount' => 10,
                'successRate' => 80.0,
                'isSuccess' => false,
                'isFailure' => false,
                'isPartial' => true,
                'isNothingToPersist' => false,
                'errors' => [
                    'timeout',
                ],
            ],
            $result->toArray(),
        );
    }

    /**
     * But : Vérifier que les erreurs dupliquées sont dédupliquées dans getErrors().
     *
     * Entrée : errors=['duplicate', 'duplicate', 'duplicate']
     * Résultat attendu : getErrors() = ['duplicate']
     */
    public function testItDeduplicatesErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 3,
            errors: [
                'duplicate',
                'duplicate',
                'duplicate',
            ],
        );

        self::assertSame(
            [
                'duplicate',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les messages d'erreur sont nettoyés des espaces superflus.
     *
     * Entrée : errors=['   sql error   ']
     * Résultat attendu : getErrors() = ['sql error']
     */
    public function testItTrimsErrorMessages(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [
                '   sql error   ',
            ],
        );

        self::assertSame(
            [
                'sql error',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les chaînes vides ou de blancs sont retirées de la liste d'erreurs.
     *
     * Entrée : errors=['', '   ', "\n", 'valid']
     * Résultat attendu : getErrors() = ['valid']
     */
    public function testItRemovesEmptyErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 2,
            errors: [
                '',
                '   ',
                "\n",
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
     * But : Vérifier que les erreurs non-scalaires (objets, tableaux, ressources) sont ignorées.
     *
     * Entrée : errors=[new \stdClass(), [], fopen(...), 'valid']
     * Résultat attendu : getErrors() = ['valid']
     */
    public function testItIgnoresNonScalarErrors(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 3,
            errors: [
                new \stdClass(),
                [],
                fopen('php://memory', 'rb'),
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
     * But : Vérifier que PersistenceResult est une instance valide et immuable.
     *
     * Entrée : PersistenceResult::success(persistedCount:1)
     * Résultat attendu : instance de PersistenceResult retournée
     */
    public function testItKeepsResultImmutable(): void
    {
        $result = PersistenceResult::success(
            persistedCount: 1,
        );

        self::assertInstanceOf(
            PersistenceResult::class,
            $result,
        );
    }

    /**
     * But : Vérifier que modifier le tableau retourné par getErrors() n'affecte pas l'état interne.
     *
     * Entrée : errors=['error'], modification externe du tableau récupéré
     * Résultat attendu : getErrors() retourne toujours ['error']
     */
    public function testItReturnsIndependentErrorsArray(): void
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
     * But : Vérifier que le taux de succès est calculé correctement avec des valeurs arrondies.
     *
     * Entrée : persistedCount=1, failedCount=3
     * Résultat attendu : getSuccessRate() = 25.0
     */
    public function testItHandlesRoundedSuccessRate(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 1,
            failedCount: 3,
        );

        self::assertSame(
            25.0,
            $result->getSuccessRate(),
        );
    }

    /**
     * But : Vérifier que partial() avec failedCount=0 produit un résultat de succès.
     *
     * Entrée : PersistenceResult::partial(persistedCount:10, failedCount:0)
     * Résultat attendu : isSuccess()=true, isPartial()=false
     */
    public function testItHandlesZeroFailureSuccessCase(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 10,
            failedCount: 0,
        );

        self::assertTrue(
            $result->isSuccess(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isFailure(),
        );
    }

    /**
     * But : Vérifier que partial() avec persistedCount=0 produit un résultat d'échec.
     *
     * Entrée : PersistenceResult::partial(persistedCount:0, failedCount:10)
     * Résultat attendu : isFailure()=true, isPartial()=false
     */
    public function testItHandlesZeroPersistedFailureCase(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: 0,
            failedCount: 10,
        );

        self::assertTrue(
            $result->isFailure(),
        );

        self::assertFalse(
            $result->isPartial(),
        );

        self::assertFalse(
            $result->isSuccess(),
        );
    }

    /**
     * But : Vérifier que failure() avec un tableau d'erreurs vide produit hasErrors()=false.
     *
     * Entrée : PersistenceResult::failure(failedCount:1, errors:[])
     * Résultat attendu : hasErrors()=false, getErrors()=[]
     */
    public function testItHandlesEmptyErrorsArray(): void
    {
        $result = PersistenceResult::failure(
            failedCount: 1,
            errors: [],
        );

        self::assertFalse(
            $result->hasErrors(),
        );

        self::assertSame(
            [],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que getTotalCount() retourne PHP_INT_MAX en cas d'overflow entier.
     *
     * Entrée : persistedCount=PHP_INT_MAX, failedCount=PHP_INT_MAX
     * Résultat attendu : getTotalCount() = PHP_INT_MAX (protection overflow)
     */
    public function testItHandlesOverflowProtection(): void
    {
        $result = PersistenceResult::partial(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertSame(
            PHP_INT_MAX,
            $result->getTotalCount(),
        );
    }
}