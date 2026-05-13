<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Crash tests robustesse du générateur.
 *
 * @covers \App\Log\Infrastructure\Queue\QueueFilenameGenerator
 */
final class QueueFilenameGeneratorCrashTest extends TestCase
{
    /**
     * But : Vérifier que 50 000 générations successives ne produisent aucune collision.
     *
     * Entrée : 50 000 appels à generate() avec horloge figée
     * Résultat attendu : Tous les 50 000 noms sont uniques
     */
    public function testMassiveGenerationDoesNotCreateCollisions(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 50000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            50000,
            array_unique($filenames),
        );
    }

    /**
     * But : Vérifier que 10 000 générations avec le même horodatage ne créent pas de collision.
     *
     * Entrée : 10 000 appels avec horloge figée (même timestamp)
     * Résultat attendu : Tous les 10 000 noms sont uniques
     */
    public function testGenerationWithSameTimestampDoesNotCollide(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 10000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            10000,
            array_unique($filenames),
        );
    }

    /**
     * But : Vérifier que 10 000 noms générés restent tous sûrs pour le filesystem.
     *
     * Entrée : 10 000 appels à generate() avec horloge figée
     * Résultat attendu : Chaque nom correspond au pattern /^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/
     */
    public function testGeneratedFilenamesRemainFilesystemSafe(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        for ($i = 0; $i < 10000; ++$i) {
            $filename = $generator->generate();

            self::assertMatchesRegularExpression(
                '/^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/',
                $filename,
            );
        }
    }

    /**
     * But : Vérifier que la longueur des noms générés ne dépasse jamais 255 caractères.
     *
     * Entrée : 10 000 appels à generate() avec horloge figée
     * Résultat attendu : Chaque nom fait ≤ 255 caractères
     */
    public function testGeneratedFilenamesRemainShortEnough(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        for ($i = 0; $i < 10000; ++$i) {
            $filename = $generator->generate();

            self::assertLessThanOrEqual(
                255,
                mb_strlen($filename),
            );
        }
    }

    /**
     * But : Vérifier que 1 000 noms générés restent triables chronologiquement.
     *
     * Entrée : 1 000 appels avec 1 ms d'avance entre chaque (clock->sleep(0.001))
     * Résultat attendu : Le tri alphabétique correspond à l'ordre de génération
     */
    public function testGeneratedFilenamesRemainChronologicallySortable(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.000000');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 1000; ++$i) {
            $filenames[] = $generator->generate();

            $clock->sleep(0.001);
        }

        $sorted = $filenames;

        sort($sorted);

        self::assertSame(
            $sorted,
            $filenames,
        );
    }
}