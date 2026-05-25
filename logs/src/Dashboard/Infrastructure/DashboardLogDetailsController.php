<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Application\GetDashboardLogDetailsHandler;
use App\Dashboard\Application\GetDashboardLogDetailsRequest;
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
}