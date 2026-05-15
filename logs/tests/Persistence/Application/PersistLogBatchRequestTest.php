<?php

declare(strict_types=1);

namespace App\Tests\Persistence\Application;

use App\Persistence\Application\PersistLogBatchRequest;
use App\Tests\Shared\Factory\LogEntryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de PersistLogBatchRequest.
 */
final class PersistLogBatchRequestTest extends TestCase
{
    /**
     * But : Vérifier que des LogEntry valides sont stockés dans leur ordre d'insertion.
     *
     * Entrée : Deux LogEntry via LogEntryFactory::create()
     * Résultat attendu : getEntries() retourne les deux entrées dans l'ordre
     */
    public function testItStoresValidEntries(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertCount(
            2,
            $request->getEntries(),
        );

        self::assertSame(
            $entry1,
            $request->getEntries()[0],
        );

        self::assertSame(
            $entry2,
            $request->getEntries()[1],
        );
    }

    /**
     * But : Vérifier que les valeurs non-LogEntry (string, int, null, stdClass) sont filtrées.
     *
     * Entrée : Un LogEntry + 'invalid', 123, null, stdClass
     * Résultat attendu : getEntries() ne contient que le LogEntry valide
     */
    public function testItFiltersInvalidValues(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry,
            'invalid',
            123,
            null,
            new \stdClass(),
        ]);

        self::assertCount(
            1,
            $request->getEntries(),
        );

        self::assertSame(
            $entry,
            $request->getEntries()[0],
        );
    }

    /**
     * But : Vérifier que PersistLogBatchRequest avec tableau vide est reconnu comme vide.
     *
     * Entrée : []
     * Résultat attendu : isEmpty()=true, count(getEntries())=0
     */
    public function testItReturnsEmptyBatch(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertTrue(
            $request->isEmpty(),
        );

        self::assertCount(
            0,
            $request->getEntries(),
        );
    }

    /**
     * But : Vérifier que count() retourne le nombre exact de LogEntry valides.
     *
     * Entrée : 3 LogEntry via LogEntryFactory::create()
     * Résultat attendu : count() = 3
     */
    public function testItReturnsCorrectCount(): void
    {
        $request = new PersistLogBatchRequest([
            LogEntryFactory::create(),
            LogEntryFactory::create(),
            LogEntryFactory::create(),
        ]);

        self::assertSame(
            3,
            $request->count(),
        );
    }

    /**
     * But : Vérifier que first() retourne le premier LogEntry inséré.
     *
     * Entrée : entry1 (message='first'), entry2 (message='second')
     * Résultat attendu : first() = entry1
     */
    public function testItReturnsFirstEntry(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'first',
        );

        $entry2 = LogEntryFactory::create(
            message: 'second',
        );

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertSame(
            $entry1,
            $request->first(),
        );
    }

    /**
     * But : Vérifier que last() retourne le dernier LogEntry inséré.
     *
     * Entrée : entry1 (message='first'), entry2 (message='second')
     * Résultat attendu : last() = entry2
     */
    public function testItReturnsLastEntry(): void
    {
        $entry1 = LogEntryFactory::create(
            message: 'first',
        );

        $entry2 = LogEntryFactory::create(
            message: 'second',
        );

        $request = new PersistLogBatchRequest([
            $entry1,
            $entry2,
        ]);

        self::assertSame(
            $entry2,
            $request->last(),
        );
    }

    /**
     * But : Vérifier que first() retourne null quand le batch est vide.
     *
     * Entrée : []
     * Résultat attendu : first() = null
     */
    public function testItReturnsNullFirstWhenEmpty(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertNull(
            $request->first(),
        );
    }

    /**
     * But : Vérifier que last() retourne null quand le batch est vide.
     *
     * Entrée : []
     * Résultat attendu : last() = null
     */
    public function testItReturnsNullLastWhenEmpty(): void
    {
        $request = new PersistLogBatchRequest([]);

        self::assertNull(
            $request->last(),
        );
    }

    /**
     * But : Vérifier que les clés numériques arbitraires sont ré-indexées à partir de 0.
     *
     * Entrée : [50 => entry1, 99 => entry2]
     * Résultat attendu : getEntries() a les clés 0 et 1
     */
    public function testItReindexesArrayKeys(): void
    {
        $entry1 = LogEntryFactory::create();
        $entry2 = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            50 => $entry1,
            99 => $entry2,
        ]);

        $entries = $request->getEntries();

        self::assertArrayHasKey(
            0,
            $entries,
        );

        self::assertArrayHasKey(
            1,
            $entries,
        );
    }

    /**
     * But : Vérifier que le tableau retourné par getEntries() est indépendant de l'état interne.
     *
     * Entrée : 1 LogEntry, ajout externe d'une entrée au tableau récupéré
     * Résultat attendu : getEntries() retourne toujours 1 élément
     */
    public function testEntriesCannotBeModifiedExternally(): void
    {
        $entry = LogEntryFactory::create();

        $request = new PersistLogBatchRequest([
            $entry,
        ]);

        $entries = $request->getEntries();

        $entries[] = LogEntryFactory::create();

        self::assertCount(
            1,
            $request->getEntries(),
        );
    }
}