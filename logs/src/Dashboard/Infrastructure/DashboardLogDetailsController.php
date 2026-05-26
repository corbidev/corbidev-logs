<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Application\GetDashboardLogDetailsHandler;
use App\Dashboard\Application\GetDashboardLogDetailsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Détail d'un log côté Dashboard.
 */
final class DashboardLogDetailsController extends AbstractController
{
    public function __construct(
        private readonly GetDashboardLogDetailsHandler $handler,
    ) {
    }

    #[Route('/dashboard/logs/{externalId}', name: 'dashboard_logs_show', methods: ['GET'])]
    public function __invoke(
        string $externalId,
    ): Response {
        $log = $this->handler->handle(
            new GetDashboardLogDetailsRequest(
                externalId: $externalId,
            ),
        );

        if ($log === null) {
            return new Response(
                sprintf(
                    'Log "%s" not found.',
                    $externalId,
                ),
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->render(
            'dashboard/log_details.html.twig',
            [
                'log' => $log,
            ],
        );
    }

    #[Route('/dashboard/logs/{externalId}/json', name: 'dashboard_logs_show_json', methods: ['GET'])]
    public function showJson(string $externalId): JsonResponse
    {
        $log = $this->handler->handle(
            new GetDashboardLogDetailsRequest(
                externalId: $externalId,
            ),
        );

        if ($log === null) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'Log not found.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse(
            [
                'success' => true,
                'data' => [
                    'externalId' => $log->getExternalId(),
                    'projectId' => $log->getProjectId(),
                    'fingerprint' => $log->getFingerprint(),
                    'requestId' => $log->getRequestId(),
                    'level' => $log->getLevel(),
                    'httpStatus' => $log->getHttpStatus(),
                    'domain' => $log->getDomain(),
                    'uri' => $log->getUri(),
                    'method' => $log->getMethod(),
                    'userAgent' => $log->getUserAgent(),
                    'env' => $log->getEnv(),
                    'client' => $log->getClient(),
                    'message' => $log->getMessage(),
                    'context' => $log->getContext(),
                    'extra' => $log->getExtra(),
                    'ingestionWarnings' => $log->getIngestionWarnings(),
                    'createdAt' => $log->getCreatedAt()->format(DATE_ATOM),
                    'clientDate' => $log->getClientDate()?->format(DATE_ATOM),
                    'ip' => $log->getIp(),
                ],
            ],
        );
    }
}