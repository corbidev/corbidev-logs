<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Queue\QueueFilenameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @covers \App\Log\Infrastructure\Queue\QueueFilenameGenerator
 */
final class QueueFilenameGeneratorTest extends TestCase
{
    /**
     * But : Vérifier que generate() retourne une chaîne de caractères.
     *
     * Entrée : Horloge figée au 2026-05-10 01:30:15.654321
     * Résultat attendu : Le résultat est de type string
     */
    public function testGenerateReturnsString(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertIsString($filename);
    }

    /**
     * But : Vérifier que le nom de fichier généré se termine par '.json'.
     *
     * Entrée : Horloge figée au 2026-05-10 01:30:15.654321
     * Résultat attendu : Le fichier se termine par '.json'
     */
    public function testGenerateReturnsJsonFilename(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertStringEndsWith('.json', $filename);
    }

    /**
     * But : Vérifier que le nom de fichier respecte le format attendu (date_heure_microsecondes_hex.json).
     *
     * Entrée : Horloge figée au 2026-05-10 01:30:15.654321
     * Résultat attendu : Le nom correspond au pattern /^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/
     */
    public function testGenerateMatchesExpectedFormat(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertMatchesRegularExpression(
            '/^\d{8}_\d{6}_\d{6}_[a-f0-9]{12}\.json$/',
            $filename,
        );
    }

    /**
     * But : Vérifier que le nom de fichier ne contient que des caractères sûrs pour le filesystem.
     *
     * Entrée : Horloge figée au 2026-05-10 01:30:15.654321
     * Résultat attendu : Aucun caractère interdit dans le nom généré
     */
    public function testGenerateReturnsFilesystemSafeFilename(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filename = $generator->generate();

        self::assertDoesNotMatchRegularExpression(
            '/[^a-zA-Z0-9_.-]/',
            $filename,
        );
    }

    /**
     * But : Vérifier que 1 000 appels successifs produisent des noms uniques.
     *
     * Entrée : 1 000 appels à generate() avec la même horloge figée
     * Résultat attendu : Tous les 1 000 noms sont distincts
     */
    public function testGenerateProducesUniqueFilenames(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.654321');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 1000; ++$i) {
            $filenames[] = $generator->generate();
        }

        self::assertCount(
            1000,
            array_unique($filenames),
        );
    }

    /**
     * But : Vérifier que les noms générés restent triables chronologiquement.
     *
     * Entrée : 100 noms générés avec avance d'1 ms entre chaque (clock->sleep(0.001))
     * Résultat attendu : Le tri alphabétique correspond à l'ordre de génération
     */
    public function testGenerateProducesChronologicallySortableFilenames(): void
    {
        $clock = new MockClock('2026-05-10 01:30:15.000000');

        $generator = new QueueFilenameGenerator($clock);

        $filenames = [];

        for ($i = 0; $i < 100; ++$i) {
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
