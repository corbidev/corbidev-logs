<?php

declare(strict_types=1);

namespace App\Tests\Unit\Persistence\Application;

use App\Persistence\Application\PersistLogBatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests de PersistLogBatchResult.
 *
 * OBJECTIFS :
 * ------------
 * Vérifier la robustesse face à :
 * - données hostiles
 * - UTF-8 invalide
 * - payload massif
 * - allocations importantes
 * - erreurs anormales
 *
 * IMPORTANT :
 * ------------
 * Le composant ne doit jamais :
 * - crasher
 * - exploser mémoire
 * - produire d'état incohérent
 */
final class PersistLogBatchResultCrashTest extends TestCase
{
    /**
     * But : Vérifier que 10 000 erreurs distinctes sont toutes conservées sans crash.
     *
     * Entrée : 10 000 erreurs 'error-$i'
     * Résultat attendu : count(getErrors()) = 10000
     */
    public function testItHandlesHugeErrorList(): void
    {
        $errors = [];

        for ($i = 0; $i < 10000; ++$i) {
            $errors[] = 'error-'.$i;
        }

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 10000,
            errors: $errors,
        );

        self::assertCount(10000, $result->getErrors());
    }

    /**
     * But : Vérifier qu'un payload d'emojis de grande taille est géré sans crash.
     *
     * Entrée : errors=[str_repeat('🔥', 10000)]
     * Résultat attendu : getErrors() non vide, aucun crash
     */
    public function testItHandlesHugeUnicodePayload(): void
    {
        $huge = str_repeat('🔥', 10000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$huge],
        );

        self::assertNotEmpty($result->getErrors());
    }

    /**
     * But : Vérifier que du contenu binaire dans les erreurs ne cause pas de crash.
     *
     * Entrée : errors=[random_bytes(512)]
     * Résultat attendu : getErrors() est un tableau
     */
    public function testItHandlesBinaryPayload(): void
    {
        $binary = random_bytes(512);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$binary],
        );

        self::assertIsArray($result->getErrors());
    }

    /**
     * But : Vérifier que du UTF-8 invalide dans les erreurs ne cause pas de crash.
     *
     * Entrée : errors=["\xB1\x31"]
     * Résultat attendu : getErrors() est un tableau
     */
    public function testItHandlesInvalidUtf8(): void
    {
        $invalidUtf8 = "\xB1\x31";

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$invalidUtf8],
        );

        self::assertIsArray($result->getErrors());
    }

    /**
     * But : Vérifier que la fusion de 5 000 résultats produit les compteurs et erreurs corrects.
     *
     * Entrée : 5 000 résultats avec persistedCount=1, failedCount=1, errors=['error-$i']
     * Résultat attendu : persistedCount=5000, failedCount=5000, count(errors)=5000
     */
    public function testItHandlesVeryLargeMergeOperation(): void
    {
        $results = [];

        for ($i = 0; $i < 5000; ++$i) {
            $results[] = new PersistLogBatchResult(
                persistedCount: 1,
                failedCount: 1,
                errors: [
                    'error-'.$i,
                ],
            );
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(5000, $merged->getPersistedCount());
        self::assertSame(5000, $merged->getFailedCount());
        self::assertCount(5000, $merged->getErrors());
    }

    /**
     * But : Vérifier qu'une erreur de 10 millions de caractères est tronquée à 1 000.
     *
     * Entrée : errors=[str_repeat('X', 10_000_000)]
     * Résultat attendu : mb_strlen(getErrors()[0]) = 1000
     */
    public function testItHandlesMassiveErrorMessage(): void
    {
        $payload = str_repeat('X', 10_000_000);

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$payload],
        );

        self::assertSame(
            1000,
            mb_strlen($result->getErrors()[0]),
        );
    }

    /**
     * But : Vérifier que PHP_INT_MAX comme compteur ne provoque pas de crash.
     *
     * Entrée : persistedCount=PHP_INT_MAX, failedCount=PHP_INT_MAX
     * Résultat attendu : getPersistedCount() et getFailedCount() > 0
     */
    public function testItHandlesExtremeCounters(): void
    {
        $result = new PersistLogBatchResult(
            persistedCount: PHP_INT_MAX,
            failedCount: PHP_INT_MAX,
        );

        self::assertGreaterThan(0, $result->getPersistedCount());
        self::assertGreaterThan(0, $result->getFailedCount());
    }

    /**
     * But : Vérifier que 100 fusions de 100 résultats chacune (10 000 au total) ne crash pas.
     *
     * Entrée : 100 × merge([100 × success(1)])
     * Résultat attendu : persistedCount = 10000
     */
    public function testItHandlesRecursiveMergeSafely(): void
    {
        $results = [];

        for ($i = 0; $i < 100; ++$i) {
            $inner = [];

            for ($j = 0; $j < 100; ++$j) {
                $inner[] = PersistLogBatchResult::success(1);
            }

            $results[] = PersistLogBatchResult::merge($inner);
        }

        $merged = PersistLogBatchResult::merge($results);

        self::assertSame(10000, $merged->getPersistedCount());
    }

    /**
     * But : Vérifier que les octets nuls dans les erreurs ne causent pas de crash.
     *
     * Entrée : errors=["error\0hidden"]
     * Résultat attendu : getErrors() non vide
     */
    public function testItHandlesNullByteInjection(): void
    {
        $payload = "error\0hidden";

        $result = new PersistLogBatchResult(
            persistedCount: 0,
            failedCount: 1,
            errors: [$payload],
        );

        self::assertNotEmpty($result->getErrors());
    }

    /**
     * But : Vérifier que 10 000 appels successifs aux getters ne causent pas de changement d'état.
     *
     * Entrée : PersistLogBatchResult::success(42), 10 000 itérations
     * Résultat attendu : persistedCount=42, failedCount=0, hasFailures()=false à chaque appel
     */
    public function testItRemainsStableUnderRepeatedCalls(): void
    {
        $result = PersistLogBatchResult::success(42);

        for ($i = 0; $i < 10000; ++$i) {
            self::assertSame(42, $result->getPersistedCount());
            self::assertSame(0, $result->getFailedCount());
            self::assertFalse($result->hasFailures());

            $array = $result->toArray();

            self::assertSame(42, $array['persisted_count']);
        }
    }
}