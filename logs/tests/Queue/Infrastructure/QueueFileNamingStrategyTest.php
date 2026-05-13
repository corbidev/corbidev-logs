<?php

declare(strict_types=1);

namespace App\Tests\Queue\Infrastructure;

use App\Queue\Infrastructure\QueueFileNamingStrategy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests fonctionnels de QueueFileNamingStrategy.
 */
final class QueueFileNamingStrategyTest extends TestCase
{
    /**
     * But : Vérifier que le nom de fichier généré respecte le format attendu.
     *
     * Entrée : QueueFileNamingStrategy::generate() avec paramètres par défaut
     * Résultat attendu : filename correspond à /^\d{8}_\d{6}_[a-f0-9]{8}\.json$/
     */
    public function test_it_generates_valid_filename(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertMatchesRegularExpression(
            '/^\d{8}_\d{6}_[a-f0-9]{8}\.json$/',
            $filename,
        );
    }

    /**
     * But : Vérifier que l'extension personnalisée est appliquée dans le nom de fichier.
     *
     * Entrée : extension = 'queue'
     * Résultat attendu : filename correspond à /^\d{8}_\d{6}_[a-f0-9]{8}\.queue$/
     */
    public function test_it_generates_filename_with_custom_extension(): void
    {
        $strategy = new QueueFileNamingStrategy(
            extension: 'queue',
        );

        $filename = $strategy->generate();

        self::assertMatchesRegularExpression(
            '/^\d{8}_\d{6}_[a-f0-9]{8}\.queue$/',
            $filename,
        );
    }

    /**
     * But : Vérifier que 1 000 appels génèrent des noms de fichiers tous distincts.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : Tous les noms sont uniques (count = 1000 après array_unique)
     */
    public function test_it_generates_unique_filenames(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $generated = [];

        for ($i = 0; $i < 1000; ++$i) {
            $filename = $strategy->generate();

            self::assertArrayNotHasKey(
                $filename,
                $generated,
            );

            $generated[$filename] = true;
        }

        self::assertCount(1000, $generated);
    }

    /**
     * But : Vérifier que les noms générés ne contiennent pas de caractères non-sûrs.
     *
     * Entrée : Appels à generate()
     * Résultat attendu : Aucun caractère '/', '\\', ':', '*', '?', '"', '<', '>', '|'
     */
    public function test_it_generates_filesystem_safe_names(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertDoesNotMatchRegularExpression(
            '/[^a-zA-Z0-9._]/',
            $filename,
        );
    }

    /**
     * But : Vérifier que l'extension par défaut est '.json'.
     *
     * Entrée : generate() sans paramètre d'extension
     * Résultat attendu : filename se termine par '.json'
     */
    public function test_it_generates_json_extension_by_default(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertStringEndsWith(
            '.json',
            $filename,
        );
    }

    /**
     * But : Vérifier que le timestamp du premier fichier est inférieur à celui du second après sleep(1).
     *
     * Entrée : generate(), sleep(1), generate()
     * Résultat attendu : firstTimestamp < secondTimestamp (tri lexicographique stable)
     */
    public function test_it_generates_orderable_timestamps(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $first = $strategy->generate();

        sleep(1);

        $second = $strategy->generate();

        $firstTimestamp = substr($first, 0, 15);
        $secondTimestamp = substr($second, 0, 15);

        self::assertLessThan(
            $secondTimestamp,
            $firstTimestamp,
        );
    }

    /**
     * But : Vérifier que le suffixe aléatoire a toujours 8 caractères.
     *
     * Entrée : 1000 appels à generate()
     * Résultat attendu : La partie random de chaque nom a exactement 8 caractères
     */
    public function test_it_generates_fixed_random_suffix_length(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        preg_match(
            '/^\d{8}_\d{6}_([a-f0-9]{8})\.json$/',
            $filename,
            $matches,
        );

        self::assertArrayHasKey(1, $matches);

        self::assertSame(
            8,
            strlen($matches[1]),
        );
    }

    /**
     * But : Vérifier que le suffixe aléatoire utilise uniquement des hexadécimaux minuscules.
     *
     * Entrée : Appel à generate()
     * Résultat attendu : La partie random correspond à /_[a-f0-9]{8}\./
     */
    public function test_it_uses_lowercase_hexadecimal_suffix(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertMatchesRegularExpression(
            '/_[a-f0-9]{8}\./',
            $filename,
        );
    }

    /**
     * But : Vérifier que 500 noms générés sont tous au format valide.
     *
     * Entrée : 500 appels à generate()
     * Résultat attendu : Chaque nom correspond au format attendu
     */
    public function test_it_generates_multiple_valid_names(): void
    {
        $strategy = new QueueFileNamingStrategy();

        for ($i = 0; $i < 500; ++$i) {
            self::assertMatchesRegularExpression(
                '/^\d{8}_\d{6}_[a-f0-9]{8}\.json$/',
                $strategy->generate(),
            );
        }
    }

    /**
     * But : Vérifier qu'aucun nom généré ne contient d'espace.
     *
     * Entrée : Appels à generate()
     * Résultat attendu : Aucun espace dans les noms générés
     */
    public function test_it_does_not_generate_whitespace(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertDoesNotMatchRegularExpression(
            '/\s/',
            $filename,
        );
    }
}