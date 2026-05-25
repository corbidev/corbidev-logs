<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Search\Application\SearchLogsHandler;
use App\Search\Application\SearchLogsRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liste paginée des logs côté Dashboard.
 */
final class DashboardLogsController extends AbstractController
{
    public function __construct(
        private readonly SearchLogsHandler $searchLogsHandler,
    ) {
    }

    #[Route('/dashboard/logs', name: 'dashboard_logs_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $page = max(
            1,
            (int) $request->query->get('page', 1),
        );

        $perPage = max(
            1,
            min(
                200,
                (int) $request->query->get('per_page', 25),
            ),
        );

        $result = $this->searchLogsHandler->handle(
            new SearchLogsRequest(
                page: $page,
                perPage: $perPage,
            ),
        );

        $totalPages = max(
            1,
            (int) ceil(
                $result->getTotalCount() / $result->getPerPage(),
            ),
        );

        return $this->render(
            'dashboard/logs.html.twig',
            [
                'items' => $result->getItems(),
                'page' => $result->getPage(),
                'perPage' => $result->getPerPage(),
                'totalCount' => $result->getTotalCount(),
                'totalPages' => $totalPages,
            ],
        );
    }
}
