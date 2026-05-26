<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure;

use OpenApi\Attributes as OA;
use App\ApiToken\Application\ValidateApiTokenHandler;
use App\ApiToken\Application\ValidateApiTokenRequest;
use App\Ingestion\Domain\IngestionPayloadValidator;
use App\Log\Application\Ingestion\LogIngestionPipeline;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint HTTP d'ingestion.
 *
 * Responsabilités :
 * - exposer POST /api/logs
 * - garantir une surface JSON-only
 * - retourner des réponses stables (succès/erreur)
 * - ne jamais renvoyer de HTML
 */
#[OA\Tag(name: 'Logs')]
final class ApiLogsController
{
    public function __construct(
        private readonly ValidateApiTokenHandler $validateApiTokenHandler,
        private readonly IngestionPayloadValidator $payloadValidator,
        private readonly LogIngestionPipeline $ingestionPipeline,
    ) {}

    /**
     * Reçoit une requête d'ingestion JSON.
     *
     * Comportement :
     * - 415 si le Content-Type n'est pas JSON
     * - 400 si le body JSON est invalide
     * - 202 si la requête est acceptée et queueée
     */
    #[Route('/api/logs', name: 'api_ingestion_logs', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/logs',
        summary: 'Ingestion d\'un log',
        description: 'Reçoit un log distant et le place en queue.',
        tags: ['Logs']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['message', 'level'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Database timeout'
                ),
                new OA\Property(
                    property: 'level',
                    type: 'string',
                    example: 'error'
                ),
                new OA\Property(
                    property: 'env',
                    type: 'string',
                    example: 'prod'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 202,
        description: 'Log accepté'
    )]
    #[OA\Response(
        response: 400,
        description: 'Payload invalide'
    )]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isJsonRequest($request)) {
            return $this->errorResponse(
                error: 'unsupported_media_type',
                message: 'Content-Type must be application/json.',
                status: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        $plainToken = $this->extractBearerToken(
            $request,
        );

        if ($plainToken === null) {
            return $this->errorResponse(
                error: 'unauthorized',
                message: 'Authorization header with Bearer token is required.',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $tokenValidationResult = $this->validateApiTokenHandler->handle(
            new ValidateApiTokenRequest($plainToken),
        );

        if ($tokenValidationResult->isRefused()) {
            return $this->errorResponse(
                error: 'unauthorized',
                message: sprintf(
                    'Token rejected: %s.',
                    $tokenValidationResult->getReason(),
                ),
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $payload = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->errorResponse(
                error: 'invalid_json',
                message: 'Request body must be valid JSON.',
                status: Response::HTTP_BAD_REQUEST,
            );
        }

        $validationError = $this->payloadValidator->validate(
            $payload,
        );

        if ($validationError !== null) {
            return $this->errorResponse(
                error: 'invalid_payload',
                message: $validationError,
                status: Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $this->ingestionPipeline->process(
            $payload['logs'],
        );

        return new JsonResponse(
            [
                'success' => true,
                'data' => [
                    'received' => $result['queued'],
                ],
            ],
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Construit une erreur API stable et JSON-only.
     */
    private function errorResponse(
        string $error,
        string $message,
        int $status,
    ): JsonResponse {
        return new JsonResponse(
            [
                'success' => false,
                'error' => $error,
                'message' => $message,
            ],
            $status,
        );
    }

    /**
     * Vérifie si la requête annonce un media type JSON.
     */
    private function isJsonRequest(Request $request): bool
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        $normalizedContentType = strtolower(trim($contentType));

        if ($normalizedContentType === '') {
            return false;
        }

        if (str_contains($normalizedContentType, 'application/json')) {
            return true;
        }

        return str_contains($normalizedContentType, '+json');
    }

    /**
     * Extrait le token Bearer depuis Authorization.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization', '');

        if ($authorization === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));

        if ($token === '') {
            return null;
        }

        return $token;
    }
}
