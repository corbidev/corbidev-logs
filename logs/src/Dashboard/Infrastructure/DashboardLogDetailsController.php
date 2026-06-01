<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Application\GetDashboardLogDetailsHandler;
use App\Dashboard\Application\GetDashboardLogDetailsRequest;
use App\Dashboard\Domain\DashboardLogDetailsView;
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
        $log = $this->findLogByExternalId($externalId);

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
            'dashboard/pages/logs/show.html.twig',
            [
                'log' => $log,
            ],
        );
    }

    #[Route('/dashboard/htmx/logs/{externalId}/detail', name: 'dashboard_logs_fragment_detail', methods: ['GET'])]
    public function detailFragment(string $externalId): Response
    {
        $log = $this->findLogByExternalId($externalId);

        if ($log === null) {
            return new Response(
                'Log introuvable.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->render(
            'dashboard/partials/logs/_detail_panel.html.twig',
            [
                'log' => $log,
            ],
        );
    }

    private function findLogByExternalId(string $externalId): ?DashboardLogDetailsView
    {
        return $this->handler->handle(
            new GetDashboardLogDetailsRequest(
                externalId: $externalId,
            ),
        );
    }
}
