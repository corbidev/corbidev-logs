<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Infrastructure;

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
     * But : Reduire la verbosite Symfony pour eviter les tests risky.
     *
     * Entree : Initialisation du test kernel.
     * Resultat attendu : Aucun output parasite dans les tests HTTP.
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';
    }

    /**
     * But : Verifier que la route accepte un POST JSON valide.
     *
     * Entree : POST /api/logs avec Content-Type application/json et body JSON correct.
     * Resultat attendu : HTTP 200, Content-Type JSON, payload de succes stable.
     */
    public function test_it_accepts_post_json_request(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['message' => 'hello'],
                JSON_THROW_ON_ERROR,
            ),
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'application/json',
            ),
        );

        self::assertJsonStringEqualsJsonString(
            '{"success":true,"data":{"status":"accepted"}}',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Verifier qu'un JSON invalide retourne une erreur lisible et stable.
     *
     * Entree : POST /api/logs avec Content-Type application/json et body casse.
     * Resultat attendu : HTTP 400, Content-Type JSON, code erreur invalid_json.
     */
    public function test_it_returns_stable_error_for_invalid_json(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
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
     * But : Verifier qu'un Content-Type non JSON est refuse proprement.
     *
     * Entree : POST /api/logs avec Content-Type text/plain.
     * Resultat attendu : HTTP 415, Content-Type JSON, code erreur unsupported_media_type.
     */
    public function test_it_returns_stable_error_for_non_json_content_type(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'application/json',
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
}
