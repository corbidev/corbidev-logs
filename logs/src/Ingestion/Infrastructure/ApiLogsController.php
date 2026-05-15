<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure;

use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint HTTP d'ingestion.
 *
 * Responsabilites :
 * - exposer POST /api/logs
 * - garantir une surface JSON-only
 * - retourner des reponses stables (succes/erreur)
 * - ne jamais renvoyer de HTML
 */
final class ApiLogsController
{
    /**
     * Recoit une requete d'ingestion JSON.
     *
     * Comportement :
     * - 415 si le Content-Type n'est pas JSON
     * - 400 si le body JSON est invalide
     * - 200 si la requete est syntaxiquement valide
     */
    #[Route('/api/logs', name: 'api_ingestion_logs', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isJsonRequest($request)) {
            return $this->errorResponse(
                error: 'unsupported_media_type',
                message: 'Content-Type must be application/json.',
                status: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        try {
            json_decode(
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

        return new JsonResponse(
            [
                'success' => true,
                'data' => [
                    'status' => 'accepted',
                ],
            ],
            Response::HTTP_OK,
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
     * Verifie si la requete annonce un media type JSON.
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
}
