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
    public function test_it_rejects_empty_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '',
        );
    }

    public function test_it_rejects_blank_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '   ',
        );
    }

    public function test_it_rejects_extension_with_dot(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueFileNamingStrategy(
            extension: '.json',
        );
    }

    #[DataProvider('invalidExtensionProvider')]
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