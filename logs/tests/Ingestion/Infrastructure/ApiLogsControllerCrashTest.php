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
 * Crash tests de l'endpoint POST /api/logs.
 *
 * Objectifs :
 * - garantir l'absence de réponse HTML
 * - sécuriser les payloads hostiles
 * - vérifier l'absence d'erreur 500 sur entrées non fiables
 * - valider la stabilité des réponses JSON
 */
final class ApiLogsControllerCrashTest extends WebTestCase
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
     * But : Vérifier que des payloads hostiles sous Content-Type JSON ne provoquent jamais d'erreur 500.
     *
     * Entrée : Plusieurs bodies hostiles ou incohérents envoyés en application/json.
     * Résultat attendu : HTTP 202 ou 400, Content-Type JSON, aucun HTML dans la réponse.
     */
    public function test_it_never_returns_html_for_hostile_json_bodies(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $payloads = [
            '{',
            '{"logs": [}',
            "\xB1\x31",
            json_encode(
                [
                    'logs' => [
                        [
                            'message' => str_repeat('X', 200000),
                        ],
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
            '{"message":"<script>alert(1)</script>"}',
            '{"message":"../../../etc/passwd"}',
        ];

        foreach ($payloads as $payload) {
            $client->request(
                'POST',
                '/api/logs',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
                ],
                content: $payload,
            );

            $response = $client->getResponse();

            self::assertContains(
                $response->getStatusCode(),
                [
                    Response::HTTP_ACCEPTED,
                    Response::HTTP_BAD_REQUEST,
                ],
            );

            self::assertTrue(
                $response->headers->contains(
                    'content-type',
                    'application/json',
                ),
            );

            self::assertStringNotContainsStringIgnoringCase(
                '<html',
                $response->getContent() ?: '',
            );
        }
    }

    /**
     * But : Vérifier qu'une rafale de requêtes hostiles reste stable et ne produit pas de crash applicatif.
     *
     * Entrée : 250 requêtes POST /api/logs avec alternance de JSON valide et invalide.
     * Résultat attendu : Aucune erreur 500, réponses toujours JSON.
     */
    public function test_it_survives_high_frequency_hostile_requests(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        for ($index = 0; $index < 250; ++$index) {
            $payload = $index % 2 === 0
                ? sprintf('{"logs":[{"message":"stress-%d"}]}', $index)
                : '{"logs": [}';

            $client->request(
                'POST',
                '/api/logs',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
                ],
                content: $payload,
            );

            $response = $client->getResponse();

            self::assertNotSame(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $response->getStatusCode(),
            );

            self::assertTrue(
                $response->headers->contains(
                    'content-type',
                    'application/json',
                ),
            );
        }
    }

    /**
     * But : Vérifier qu'un Content-Type exotique non JSON est toujours refusé proprement sans HTML.
     *
     * Entrée : POST /api/logs avec plusieurs Content-Type non JSON hostiles ou incohérents.
     * Résultat attendu : HTTP 415, réponse JSON stable, aucun HTML.
     */
    public function test_it_rejects_non_json_content_types_without_html(): void
    {
        $client = $this->createClientWithApiTokenValidation();

        $contentTypes = [
            '',
            'text/plain',
            'application/xml',
            'multipart/form-data',
            'text/html',
        ];

        foreach ($contentTypes as $contentType) {
            $client->request(
                'POST',
                '/api/logs',
                server: [
                    'CONTENT_TYPE' => $contentType,
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer test-valid-token',
                ],
                content: 'hostile-body',
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

            self::assertStringNotContainsStringIgnoringCase(
                '<html',
                $response->getContent() ?: '',
            );
        }
    }

    /**
     * Crée un client HTTP avec validation token pilotée par doubles de test.
     */
    private function createClientWithApiTokenValidation(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

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
}
