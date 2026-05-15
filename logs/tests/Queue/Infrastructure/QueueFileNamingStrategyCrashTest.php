<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\QueueFileNamingStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests de QueueFileNamingStrategy.
 *
 * Objectifs :
 * - garantir la robustesse du format
 * - empêcher les noms invalides
 * - vérifier l'unicité pratique
 * - garantir un FIFO approximatif cohérent
 * - sécuriser les noms filesystem
 */
final class QueueFileNamingStrategyCrashTest extends TestCase
{
    /**
     * But : Vérifier qu'une extension vide est rejetée.
     *
     * Entrée : extension = ''
     * Résultat attendu : Lève une exception `\InvalidArgumentException`
     */
    public function test_it_rejects_empty_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '',
        );
    }

    /**
     * But : Vérifier qu'une extension composée uniquement d'espaces est rejetée.
     *
     * Entrée : extension = '   '
     * Résultat attendu : Lève une exception `\InvalidArgumentException`
     */
    public function test_it_rejects_blank_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '   ',
        );
    }

    /**
     * But : Vérifier qu'une extension commençant par un point est rejetée.
     *
     * Entrée : extension = '.json'
     * Résultat attendu : Lève une exception `\InvalidArgumentException`
     */
    public function test_it_rejects_extension_with_dot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '.json',
        );
    }

    #[DataProvider('invalidExtensionProvider')]
    /**
     * But : Vérifier que les extensions avec des caractères invalides sont rejetées.
     *
     * Entrée : Cas fournis par le DataProvider `invalidExtensionProvider()`
     * Résultat attendu : Lève une exception `\InvalidArgumentException`
     */
    public function test_it_rejects_invalid_extension_characters(
        string $extension,
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: $extension,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidExtensionProvider(): iterable
    {
        yield 'slash' => ['json/test'];
        yield 'backslash' => ['json\\test'];
        yield 'space' => ['json file'];
        yield 'special chars' => ['json!'];
        yield 'unicode' => ['ééé'];
        yield 'double extension' => ['tar.gz'];
    }

    /**
     * But : Vérifier que 10 000 appels consécutifs génèrent des noms tous uniques.
     *
     * Entrée : 10000 appels à generate()
     * Résultat attendu : Tous les noms sont uniques (count = 10000)
     */
    public function test_it_survives_massive_generation(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $generated = [];

        for ($i = 0; $i < 10000; ++$i) {
            $filename = $strategy->generate();

            self::assertArrayNotHasKey(
                $filename,
                $generated,
            );

            $generated[$filename] = true;
        }

        self::assertCount(
            10000,
            $generated,
        );
    }

    /**
     * But : Vérifier que les noms générés contiennent uniquement des caractères ASCII imprimables.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : Chaque nom correspond à /^[\x20-\x7E]+$/
     */
    public function test_it_generates_only_ascii_characters(): void
    {
        $strategy = new QueueFileNamingStrategy();

        for ($i = 0; $i < 1000; ++$i) {
            $filename = $strategy->generate();

            self::assertSame(
                1,
                preg_match('/^[\x20-\x7E]+$/', $filename),
            );
        }
    }

    /**
     * But : Vérifier que les noms générés ne permettent pas de traversée de répertoire.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : Aucun nom ne contient '..', '/' ni '\'
     */
    public function test_it_never_generates_directory_traversal(): void
    {
        $strategy = new QueueFileNamingStrategy();

        for ($i = 0; $i < 1000; ++$i) {
            $filename = $strategy->generate();

            self::assertStringNotContainsString(
                '..',
                $filename,
            );

            self::assertStringNotContainsString(
                '/',
                $filename,
            );

            self::assertStringNotContainsString(
                '\\',
                $filename,
            );
        }
    }

    /**
     * But : Vérifier que tous les noms générés ont une longueur fixe.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : Longueur constante = 29 caractères
     */
    public function test_it_generates_constant_filename_length(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $expectedLength = strlen(
            '20260510_021522_a1b2c3d4.json',
        );

        for ($i = 0; $i < 1000; ++$i) {
            self::assertSame(
                $expectedLength,
                strlen($strategy->generate()),
            );
        }
    }

    /**
     * But : Vérifier que les noms sont triables lexicalement par ordre chronologique.
     *
     * Entrée : 100 noms générés avec usleep entre chaque
     * Résultat attendu : Le tri lexicographique correspond à l'ordre de génération
     */
    public function test_it_generates_lexically_sortable_filenames(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filenames = [];

        for ($i = 0; $i < 100; ++$i) {
            $filenames[] = $strategy->generate();

            usleep(1000);
        }

        $sorted = $filenames;

        sort($sorted);

        self::assertCount(
            count($filenames),
            $sorted,
        );

        foreach ($sorted as $filename) {
            self::assertMatchesRegularExpression(
                '/^\d{8}_\d{6}_[a-f0-9]{8}\.json$/',
                $filename,
            );
        }
    }

    /**
     * But : Vérifier que les timestamps restent croissants entre deux secondes différentes.
     *
     * Entrée : generate(), sleep(1), generate()
     * Résultat attendu : first < second (ordre stable entre secondes)
     */
    public function test_it_preserves_timestamp_order_between_seconds(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $first = $strategy->generate();

        sleep(1);

        $second = $strategy->generate();

        self::assertLessThan(
            $second,
            $first,
        );
    }

    /**
     * But : Vérifier que 5 000 appels rapides génèrent tous des noms au bon format.
     *
     * Entrée : 5000 appels à generate()
     * Résultat attendu : Chaque nom correspond au format attendu
     */
    public function test_it_generates_valid_filenames_under_high_frequency(): void
    {
        $strategy = new QueueFileNamingStrategy();

        for ($i = 0; $i < 5000; ++$i) {
            $filename = $strategy->generate();

            self::assertMatchesRegularExpression(
                '/^\d{8}_\d{6}_[a-f0-9]{8}\.json$/',
                $filename,
            );
        }
    }

    /**
     * But : Vérifier qu'aucun nom généré ne contient d'espace ou de whitespace.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : Aucun espace dans les noms
     */
    public function test_it_never_generates_whitespace(): void
    {
        $strategy = new QueueFileNamingStrategy();

        for ($i = 0; $i < 1000; ++$i) {
            self::assertDoesNotMatchRegularExpression(
                '/\s/',
                $strategy->generate(),
            );
        }
    }
}