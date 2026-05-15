<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Application;

use App\Persistence\Application\PersistLogBatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistLogBatchResult.
 *
 * OBJECTIFS :
 * ------------
 * - stabilité
 * - immutabilité
 * - prédictibilité
 * - robustesse
 * - invariants métier
 *
 * IMPORTANT :
 * ------------
 * Ces tests ne doivent JAMAIS :
 * - utiliser Symfony
 * - utiliser Doctrine
 * - utiliser une base SQL
 */
final class PersistLogBatchResultTest extends TestCase
{
    /**
     * But : Vérifier que PersistLogBatchResult::empty() crée un résultat entièrement vide.
     *
     * Entrée : Aucune
     * Résultat attendu : persistedCount=0, failedCount=0, errors=[], hasPersistedLogs()=false
     */
    public function testItCreatesEmptyResult(): void
    {
        $result = PersistLogBatchResult::empty();

        self::assertSame(0, $result->getPersistedCount());
        self::assertSame(0, $result->getFailedCount());
        self::assertSame(0, $result->getTotalCount());

        self::assertFalse($result->hasFailures());
        self::assertTrue($result->hasNoFailures());
        self::assertFalse($result->hasPersistedLogs());

        self::assertSame([], $result->getErrors());
    }

    /**
     * But : Vérifier que PersistLogBatchResult::success() crée un résultat avec des logs persistés.
     *
     * Entrée : 42
     * Résultat attendu : persistedCount=42, failedCount=0, hasPersistedLogs()=true
     */
    public function testItCreatesSuccessResult(): void
    {
        $result = PersistLogBatchResult::success(42);

        self::assertSame(42, $result->getPersistedCount());
        self::assertSame(0, $result->getFailedCount());
        self::assertSame(42, $result->getTotalCount());

        self::assertTrue($result->hasPersistedLogs());
        self::assertFalse($result->hasFailures());
        self::assertTrue($result->hasNoFailures());
    }

    /**
     * But : Vérifier que PersistLogBatchResult::failure() crée un résultat d'échec avec erreurs.
     *
     * Entrée : failedCount=12, errors=['SQL error', 'Deadlock']
     * Résultat attendu : persistedCount=0, failedCount=12, count(errors)=2
     */
    public function testItCreatesFailureResult(): void
    {
        $result = PersistLogBatchResult::failure(
            failedCount: 12,
            errors: [
                'SQL error',
                'Deadlock',
            ],
        );

        self::assertSame(0, $result->getPersistedCount());
        self::assertSame(12, $result->getFailedCount());
        self::assertSame(12, $result->getTotalCount());

        self::assertTrue($result->hasFailures());
        self::assertFalse($result->hasNoFailures());

        self::assertCount(2, $result->getErrors());
    }

    /**
     * But : Vérifier que toArray() retourne la structure attendue avec toutes les propriétés.
     *
     * Entrée : new PersistLogBatchResult(persistedCount:10, failedCount:2, errors:['error'])
     * Résultat attendu : Tableau avec persisted_count, failed_count, total_count, errors
     */
    public function testItReturnsStableArrayRepresentation(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 10,
            failedCount: 2,
            errors: ['error'],
        );

        self::assertSame(
            [
                'persisted_count' => 10,
                'failed_count' => 2,
                'total_count' => 12,
                'errors' => ['error'],
            ],
            $result->toArray(),
        );
    }

    /**
     * But : Vérifier que merge() additionne correctement plusieurs résultats.
     *
     * Entrée : Deux résultats (10+1+'error-1' et 20+2+'error-2')
     * Résultat attendu : persistedCount=30, failedCount=3, errors=['error-1', 'error-2']
     */
    public function testItMergesMultipleResults(): void
    {
        $result = PersistLogBatchResult::merge([
            new PersistLogBatchResult(
                persistedCount: 10,
                failedCount: 1,
                errors: ['error-1'],
            ),
            new PersistLogBatchResult(
                persistedCount: 20,
                failedCount: 2,
                errors: ['error-2'],
            ),
        ]);

        self::assertSame(30, $result->getPersistedCount());
        self::assertSame(3, $result->getFailedCount());
        self::assertSame(33, $result->getTotalCount());

        self::assertSame(
            [
                'error-1',
                'error-2',
            ],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier qu'un persistedCount négatif lève une InvalidArgumentException.
     *
     * Entrée : new PersistLogBatchResult(persistedCount:-1, failedCount:0)
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function testItRejectsNegativePersistedCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PersistLogBatchResult(
            persistedCount: -1,
            failedCount: 0,
        );
    }

    /**
     * But : Vérifier qu'un failedCount négatif lève une InvalidArgumentException.
     *
     * Entrée : new PersistLogBatchResult(persistedCount:0, failedCount:-1)
     * Résultat attendu : \InvalidArgumentException lancée
     */
    public function testItRejectsNegativeFailedCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: -1,
        );
    }

    /**
     * But : Vérifier que les erreurs dupliquées sont dédupliquées dans getErrors().
     *
     * Entrée : errors=['duplicate', 'duplicate', 'duplicate']
     * Résultat attendu : getErrors() = ['duplicate']
     */
    public function testItRemovesDuplicateErrors(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 2,
            errors: [
                'duplicate',
                'duplicate',
                'duplicate',
            ],
        );

        self::assertSame(
            ['duplicate'],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les erreurs vides ou de blancs sont filtrées.
     *
     * Entrée : errors=['', ' ', "\n", "\t", 'valid']
     * Résultat attendu : getErrors() = ['valid']
     */
    public function testItIgnoresEmptyErrors(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
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
            ['valid'],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les objets Stringable sont acceptés et convertis en string.
     *
     * Entrée : errors=[objet Stringable retournant 'stringable-error']
     * Résultat attendu : getErrors() = ['stringable-error']
     */
    public function testItSupportsStringableErrors(): void
    {
        $error = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-error';
            }
        };

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$error],
        );

        self::assertSame(
            ['stringable-error'],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les types invalides (tableau, stdClass, ressource) sont ignorés.
     *
     * Entrée : errors=[[], stdClass, resource, 'valid']
     * Résultat attendu : getErrors() = ['valid']
     */
    public function testItIgnoresInvalidErrorTypes(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [
                [],
                new \stdClass(),
                fopen('php://memory', 'rb'),
                'valid',
            ],
        );

        self::assertSame(
            ['valid'],
            $result->getErrors(),
        );
    }

    /**
     * But : Vérifier que les erreurs trop longues sont tronquées à 1 000 caractères.
     *
     * Entrée : errors=[str_repeat('A', 5000)]
     * Résultat attendu : mb_strlen(getErrors()[0]) = 1000
     */
    public function testItTruncatesVeryLargeErrors(): void
    {
        $huge = str_repeat('A', 5000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$huge],
        );

        self::assertSame(
            1000,
            mb_strlen($result->getErrors()[0]),
        );
    }

    /**
     * But : Vérifier que la fusion de 1 000 résultats produit les compteurs corrects.
     *
     * Entrée : 1 000 résultats de succès avec persistedCount=1
     * Résultat attendu : persistedCount=1000, failedCount=0
     */
    public function testItHandlesLargeMerge(): void
    {
        $results = [];

        for ($i = 0; $i < 1000; ++$i) {
            $results[] = new PersistLogBatchResult(
                persistedCount: 1,
                failedCount: 0,
            );
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(1000, $merged->getPersistedCount());
        self::assertSame(0, $merged->getFailedCount());
    }
}