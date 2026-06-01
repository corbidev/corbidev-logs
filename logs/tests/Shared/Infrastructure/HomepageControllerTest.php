<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class HomepageControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';
    }

    public function test_it_displays_root_landing_page_with_useful_links(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        self::assertTrue(
            $response->headers->contains('content-type', 'text/html; charset=UTF-8'),
        );

        $content = $response->getContent() ?: '';

        self::assertStringContainsString('Plateforme centrale de logs et d\'analyse', $content);
        self::assertStringContainsString('Liens utiles', $content);
        self::assertStringContainsString('Demarrage rapide ingestion', $content);

        self::assertStringContainsString('href="/dashboard/logs"', $content);
        self::assertStringContainsString('href="/admin/login"', $content);
        self::assertStringContainsString('href="/admin/tokens"', $content);
        self::assertStringContainsString('href="/admin/consumers"', $content);
        self::assertStringContainsString('href="/api/doc"', $content);
        self::assertStringContainsString('href="/api/doc.json"', $content);
    }
}
