<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\ApiToken\Application\ValidateApiTokenHandler;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenState;
use App\ApiToken\Domain\ApiTokenToStore;
use App\Queue\Application\QueueWriterInterface;
use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchLogRepositoryInterface;
use App\Search\Domain\SearchPagination;
use App\Search\Domain\SearchResult;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class ExceptionResponseSubscriberTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';
    }

    public function test_it_returns_html_page_for_web_404_errors(): void
    {
        $client = static::createClient();

        $client->request('GET', '/route-web-inexistante');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        self::assertTrue(
            $response->headers->contains('content-type', 'text/html; charset=UTF-8'),
        );

        $content = $response->getContent() ?: '';

        self::assertStringContainsString('Erreur 404', $content);
        self::assertStringContainsString('No route found for', $content);
        self::assertStringContainsString('Chemin: /route-web-inexistante', $content);
        self::assertStringContainsString('Retour a l\'accueil', $content);
        self::assertStringContainsString('href="/"', $content);
    }

    public function test_it_returns_json_payload_for_api_404_errors(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/route-inexistante',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        self::assertTrue(
            $response->headers->contains('content-type', 'application/json'),
        );

        $decoded = json_decode(
            $response->getContent() ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(false, $decoded['success'] ?? null);
        self::assertSame('http_error', $decoded['error'] ?? null);
        self::assertSame(404, $decoded['status'] ?? null);
        self::assertSame('Non trouve', $decoded['title'] ?? null);
        self::assertSame('/api/route-inexistante', $decoded['path'] ?? null);
        self::assertStringContainsString(
            'No route found for',
            (string) ($decoded['message'] ?? ''),
        );
    }

    public function test_it_returns_html_page_for_web_500_errors(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            SearchLogRepositoryInterface::class,
            new class implements SearchLogRepositoryInterface
            {
                public function search(
                    SearchPagination $pagination,
                    SearchFilters $filters,
                ): SearchResult {
                    throw new \RuntimeException('web failure');
                }
            },
        );

        $client->request('GET', '/dashboard/logs');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        self::assertTrue(
            $response->headers->contains('content-type', 'text/html; charset=UTF-8'),
        );

        $content = $response->getContent() ?: '';

        self::assertStringContainsString('Erreur 500', $content);
        self::assertStringContainsString('web failure', $content);
        self::assertStringContainsString('Chemin: /dashboard/logs', $content);
        self::assertStringContainsString('Retour a l\'accueil', $content);
        self::assertStringContainsString('href="/"', $content);
    }

    public function test_it_returns_json_payload_for_api_500_errors(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            ValidateApiTokenHandler::class,
            new ValidateApiTokenHandler(
                new class implements ApiTokenHasherInterface
                {
                    public function hash(string $plainToken): string
                    {
                        return trim($plainToken);
                    }
                },
                new class implements ApiTokenRepositoryInterface
                {
                    public function store(ApiTokenToStore $tokenToStore): void {}

                    public function resolveStateByHash(string $tokenHash, \DateTimeImmutable $now): ApiTokenState
                    {
                        if ($tokenHash === 'test-valid-token') {
                            return ApiTokenState::ACTIVE;
                        }

                        return ApiTokenState::NOT_FOUND;
                    }

                    public function revokeByHash(string $tokenHash, \DateTimeImmutable $revokedAt): bool
                    {
                        return false;
                    }
                },
            ),
        );

        static::getContainer()->set(
            QueueWriterInterface::class,
            new class implements QueueWriterInterface
            {
                public function write(array $payload): string
                {
                    throw new \RuntimeException('api queue failure');
                }
            },
        );

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
            ],
            content: json_encode(
                [
                    'logs' => [
                        [
                            'message' => 'boom',
                        ],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        self::assertTrue(
            $response->headers->contains('content-type', 'application/json'),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"http_error","status":500,"title":"Erreur interne du serveur","message":"api queue failure","path":"/api/logs"}',
            $response->getContent() ?: '',
        );
    }

    public function test_it_redirects_unauthenticated_admin_web_request_to_login(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/tokens');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/admin/login', $response->headers->get('Location'));
    }
}
