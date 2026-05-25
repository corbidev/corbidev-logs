<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Search\Application\SearchLogsHandler;
use App\Search\Application\SearchLogsRequest;
use Doctrine\DBAL\Connection;
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
        private readonly Connection $connection,
    ) {
    }

    #[Route('/dashboard/logs', name: 'dashboard_logs_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $fromDate = $this->parseDate(
            $request->query->get('from_date'),
        );

        $toDate = $this->parseDate(
            $request->query->get('to_date'),
        );

        $level = $this->sanitizeString(
            $request->query->get('level'),
        );

        $domain = $this->sanitizeString(
            $request->query->get('domain'),
        );

        $fingerprint = $this->sanitizeString(
            $request->query->get('fingerprint'),
        );

        $query = $this->sanitizeString(
            $request->query->get('q'),
        );

        $projectId = $this->parseProjectId(
            $request->query->get('project_id'),
        );

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
                fromDate: $fromDate,
                toDate: $toDate,
                level: $level,
                domain: $domain,
                projectId: $projectId,
                fingerprint: $fingerprint,
                query: $query,
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
                'filters' => [
                    'from_date' => $request->query->get('from_date', ''),
                    'to_date' => $request->query->get('to_date', ''),
                    'level' => $level ?? '',
                    'domain' => $domain ?? '',
                    'project_id' => $projectId !== null ? (string) $projectId : '',
                    'fingerprint' => $fingerprint ?? '',
                    'q' => $query ?? '',
                ],
                'levels' => $this->fetchLevels(),
                'domains' => $this->fetchDomains(),
                'projects' => $this->fetchProjects(),
                'paginationParams' => [
                    'per_page' => $perPage,
                    'from_date' => $request->query->get('from_date', ''),
                    'to_date' => $request->query->get('to_date', ''),
                    'level' => $level ?? '',
                    'domain' => $domain ?? '',
                    'project_id' => $projectId !== null ? (string) $projectId : '',
                    'fingerprint' => $fingerprint ?? '',
                    'q' => $query ?? '',
                ],
            ],
        );
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function parseProjectId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $projectId = (int) $value;

        return $projectId > 0 ? $projectId : null;
    }

    /**
     * @return array<int, string>
     */
    private function fetchLevels(): array
    {
        /** @var array<int, string> $levels */
        $levels = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT level FROM logs WHERE level <> '' ORDER BY level ASC",
        );

        return $levels;
    }

    /**
     * @return array<int, string>
     */
    private function fetchDomains(): array
    {
        /** @var array<int, string> $domains */
        $domains = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT domain FROM logs WHERE domain <> '' ORDER BY domain ASC",
        );

        return $domains;
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private function fetchProjects(): array
    {
        /** @var array<int, array{id: int, name: string, slug: string}> $projects */
        $projects = $this->connection->fetchAllAssociative(
            'SELECT id, name, slug FROM projects ORDER BY name ASC',
        );

        return $projects;
    }
}
