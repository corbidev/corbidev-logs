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
    public function test_it_generates_valid_filename(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertMatchesRegularExpression(
            '/^\d{8}_\d{6}_[a-f0-9]{8}\.json$/',
            $filename,
        );
    }

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

    public function test_it_generates_filesystem_safe_names(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertDoesNotMatchRegularExpression(
            '/[^a-zA-Z0-9._]/',
            $filename,
        );
    }

    public function test_it_generates_json_extension_by_default(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertStringEndsWith(
            '.json',
            $filename,
        );
    }

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

    public function test_it_uses_lowercase_hexadecimal_suffix(): void
    {
        $strategy = new QueueFileNamingStrategy();

        $filename = $strategy->generate();

        self::assertMatchesRegularExpression(
            '/_[a-f0-9]{8}\./',
            $filename,
        );
    }

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