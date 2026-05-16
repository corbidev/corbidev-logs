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
    }

    /**
     * But : Vérifier que la route accepte un POST JSON valide.
     *
     * Entrée : POST /api/logs avec Content-Type application/json et body JSON contenant logs.
     * Résultat attendu : HTTP 200, Content-Type JSON, payload de succès stable.
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
     * But : Vérifier qu'un payload sans champ logs est refusé proprement.
     *
     * Entrée : POST /api/logs avec body JSON sans logs.
     * Résultat attendu : HTTP 400, code erreur invalid_payload, message stable.
     */
    public function test_it_rejects_payload_without_logs(): void
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
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/logs',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
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
        $client = static::createClient();

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
     * But : Vérifier qu'un Content-Type non JSON est refusé proprement.
     *
     * Entrée : POST /api/logs avec Content-Type text/plain.
     * Résultat attendu : HTTP 415, Content-Type JSON, code erreur unsupported_media_type.
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
