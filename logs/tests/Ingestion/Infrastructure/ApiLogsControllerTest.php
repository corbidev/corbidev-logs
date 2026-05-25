<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Infrastructure;

use App\ApiToken\Application\ValidateApiTokenHandler;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenState;
use App\ApiToken\Domain\ApiTokenToStore;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * Tests fonctionnels de l'endpoint POST /api/logs.
 */
final class ApiLogsControllerTest extends WebTestCase
{
    /**
     * Répertoire des fichiers queue.
     */
    private string $queueDirectory;

    /**
     * But : Réduire la verbosité Symfony pour éviter les tests risky.
     *
     * Entrée : Initialisation du test kernel.
     * Résultat attendu : Aucun output parasite dans les tests HTTP.
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';

        $this->queueDirectory = dirname(__DIR__, 3)
            . '/var/queue/logs';

        $this->removeDirectory(
            dirname(__DIR__, 3) . '/var/queue',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(
            dirname(__DIR__, 3) . '/var/queue',
        );

        parent::tearDown();
    }

    /**
     * But : Vérifier que la route accepte un POST JSON valide.
     *
     * Entrée : POST /api/logs avec Content-Type application/json et body JSON contenant logs.
     * Résultat attendu : HTTP 202, Content-Type JSON, payload de succès stable.
     */
    public function test_it_accepts_post_json_request(): void
    {
        $client = $this->createClientWithApiTokenValidation();

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
                            'message' => 'hello',
                        ],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_ACCEPTED,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'application/json',
            ),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":true,"data":{"received":1}}',
            $response->getContent() ?: '',
        );

        $files = glob($this->queueDirectory . '/*.json');

        self::assertIsArray($files);

        self::assertCount(1, $files);

        $content = file_get_contents($files[0]);

        self::assertNotFalse($content);

        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('hello', $decoded['message'] ?? null);
        self::assertSame('prod', $decoded['environment'] ?? null);
        self::assertArrayHasKey('request', $decoded);
        self::assertArrayHasKey('ingestionWarnings', $decoded);
    }

    /**
     * But : Vérifier qu'un log hostile n'interrompt pas le flux et produit quand même un fichier queue.
     *
     * Entrée : POST /api/logs avec un log valide structurellement mais hostile dans son contenu.
     * Résultat attendu : HTTP 202 et au moins un fichier queue créé.
     */
    public function test_it_handles_hostile_log_payload_without_breaking_flow(): void
    {
        $client = $this->createClientWithApiTokenValidation();

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
                            'message' => str_repeat('<script>', 200),
                            'domain' => '../../../etc/passwd',
                            'context' => [
                                'nested' => array_fill(0, 500, 'x'),
                                'token' => 'super-secret',
                            ],
                        ],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_ACCEPTED,
            $response->getStatusCode(),
        );

        $files = glob($this->queueDirectory . '/*.json');

        self::assertIsArray($files);
        self::assertCount(1, $files);
    }

    /**
     * But : Vérifier qu'un payload sans champ logs est refusé proprement.
     *
     * Entrée : POST /api/logs avec body JSON sans logs.
     * Résultat attendu : HTTP 400, code erreur invalid_payload, message stable.
     */
    public function test_it_rejects_payload_without_logs(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
            ],
            content: json_encode(
                ['message' => 'hello'],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"invalid_payload","message":"Payload must contain a \"logs\" field."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'un champ logs vide est refusé proprement.
     *
     * Entrée : POST /api/logs avec logs=[].
     * Résultat attendu : HTTP 400, code erreur invalid_payload, message stable.
     */
    public function test_it_rejects_empty_logs_payload(): void
    {
        $client = $this->createClientWithApiTokenValidation();

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
                    'logs' => [],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"invalid_payload","message":"The \"logs\" field must not be empty."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'une entrée de log mal structurée est refusée proprement.
     *
     * Entrée : POST /api/logs avec logs contenant une scalar à l'index 0.
     * Résultat attendu : HTTP 400, code erreur invalid_payload, message stable.
     */
    public function test_it_rejects_logs_entries_that_are_not_objects(): void
    {
        $client = $this->createClientWithApiTokenValidation();

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
                        'invalid-entry',
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"invalid_payload","message":"Each log entry must be an object. Invalid entry at index 0."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'un JSON invalide retourne une erreur lisible et stable.
     *
     * Entrée : POST /api/logs avec Content-Type application/json et body cassé.
     * Résultat attendu : HTTP 400, Content-Type JSON, code erreur invalid_json.
     */
    public function test_it_returns_stable_error_for_invalid_json(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
            ],
            content: '{"logs": [}',
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'application/json',
            ),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"invalid_json","message":"Request body must be valid JSON."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'un Content-Type non JSON est refusé proprement.
     *
     * Entrée : POST /api/logs avec Content-Type text/plain.
     * Résultat attendu : HTTP 415, Content-Type JSON, code erreur unsupported_media_type.
     */
    public function test_it_returns_stable_error_for_non_json_content_type(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
            ],
            content: 'message=test',
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'application/json',
            ),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"unsupported_media_type","message":"Content-Type must be application/json."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'un POST JSON sans Authorization est refusé.
     *
     * Entrée : POST /api/logs sans header Authorization.
     * Résultat attendu : HTTP 401, erreur unauthorized, message stable.
     */
    public function test_it_rejects_request_without_authorization_header(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                [
                    'logs' => [
                        ['message' => 'hello'],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode(),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"unauthorized","message":"Authorization header with Bearer token is required."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier qu'un token inconnu est refusé explicitement.
     *
     * Entrée : POST /api/logs avec Bearer invalid-token.
     * Résultat attendu : HTTP 401, erreur unauthorized avec raison token_not_found.
     */
    public function test_it_rejects_request_with_invalid_bearer_token(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
            ],
            content: json_encode(
                [
                    'logs' => [
                        ['message' => 'hello'],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode(),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":false,"error":"unauthorized","message":"Token rejected: token_not_found."}',
            $response->getContent() ?: '',
        );
    }

    /**
     * Crée un client HTTP avec validation token pilotée par doubles de test.
     */
    private function createClientWithApiTokenValidation(): KernelBrowser
    {
        $client = static::createClient();

        $container = static::getContainer();

        $container->set(
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

                        if ($tokenHash === 'test-revoked-token') {
                            return ApiTokenState::REVOKED;
                        }

                        if ($tokenHash === 'test-expired-token') {
                            return ApiTokenState::EXPIRED;
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

        return $client;
    }

    /**
     * Supprime un répertoire de test de manière récursive.
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            @chmod($path, 0644);
            @unlink($path);
        }

        @chmod($directory, 0755);
        @rmdir($directory);
    }
}
