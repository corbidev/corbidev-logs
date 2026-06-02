<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Search\Application\SearchLogsHandler;
use App\Search\Application\SearchLogsRequest;
use App\Search\Domain\SearchResult;
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
        $viewData = $this->buildLogsViewData($request);

        return $this->render(
            'dashboard/pages/logs/index.html.twig',
            $viewData,
        );
    }

    #[Route('/dashboard/htmx/logs/list', name: 'dashboard_logs_fragment_list', methods: ['GET'])]
    public function listFragment(Request $request): Response
    {
        $viewData = $this->buildLogsViewData($request);

        return $this->render(
            'dashboard/partials/logs/_list_region.html.twig',
            $viewData,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogsViewData(Request $request): array
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

        $domainId = $this->parseDomainId(
            $request->query->get('domain_id'),
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

        $searchRequest = new SearchLogsRequest(
            page: $page,
            perPage: $perPage,
            fromDate: $fromDate,
            toDate: $toDate,
            level: $level,
            domain: $domain,
            domainId: $domainId,
            fingerprint: $fingerprint,
            query: $query,
        );

        $result = $this->searchLogsHandler->handle($searchRequest);

        return $this->buildTemplateData(
            request: $request,
            result: $result,
            level: $level,
            domain: $domain,
            domainId: $domainId,
            fingerprint: $fingerprint,
            query: $query,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTemplateData(
        Request $request,
        SearchResult $result,
        ?string $level,
        ?string $domain,
        ?int $domainId,
        ?string $fingerprint,
        ?string $query,
    ): array {

        $totalPages = max(
            1,
            (int) ceil(
                $result->getTotalCount() / $result->getPerPage(),
            ),
        );

        $filtersPanel = strtolower((string) $request->query->get('filters_panel', ''));

        if ($filtersPanel !== 'open') {
            $filtersPanel = 'closed';
        }

        return [
            'items' => $result->getItems(),
            'page' => $result->getPage(),
            'perPage' => $result->getPerPage(),
            'totalCount' => $result->getTotalCount(),
            'totalPages' => $totalPages,
            'filtersPanel' => $filtersPanel,
            'filters' => [
                'from_date' => $request->query->get('from_date', ''),
                'to_date' => $request->query->get('to_date', ''),
                'level' => $level ?? '',
                'domain' => $domain ?? '',
                'domain_id' => $domainId !== null ? (string) $domainId : '',
                'fingerprint' => $fingerprint ?? '',
                'q' => $query ?? '',
                'filters_panel' => $filtersPanel,
            ],
            'levels' => $this->fetchLevels(),
            'domains' => $this->fetchDomains(),
            'tenantDomains' => $this->fetchTenantDomains(),
            'paginationParams' => [
                'per_page' => $result->getPerPage(),
                'from_date' => $request->query->get('from_date', ''),
                'to_date' => $request->query->get('to_date', ''),
                'level' => $level ?? '',
                'domain' => $domain ?? '',
                'domain_id' => $domainId !== null ? (string) $domainId : '',
                'fingerprint' => $fingerprint ?? '',
                'q' => $query ?? '',
                'filters_panel' => $filtersPanel,
            ],
        ];
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

    private function parseDomainId(mixed $domainValue): ?int
    {
        if (is_int($domainValue)) {
            return $domainValue > 0 ? $domainValue : null;
        }

        if (is_string($domainValue) && ctype_digit($domainValue)) {
            $domainId = (int) $domainValue;

            return $domainId > 0 ? $domainId : null;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function fetchLevels(): array
    {
        try {
            /** @var array<int, string> $levels */
            $levels = $this->connection->fetchFirstColumn(
                "SELECT DISTINCT level FROM logs WHERE level <> '' ORDER BY level ASC",
            );

            return $levels;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchDomains(): array
    {
        try {
            /** @var array<int, string> $domains */
            $domains = $this->connection->fetchFirstColumn(
                "SELECT slug FROM domains WHERE slug <> '' ORDER BY slug ASC",
            );

            return $domains;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private function fetchTenantDomains(): array
    {
        try {
            /** @var array<int, array{id: int, name: string, slug: string}> $domains */
            $domains = $this->connection->fetchAllAssociative(
                'SELECT id, name, slug FROM domains ORDER BY name ASC',
            );

            return $domains;
        } catch (\Throwable) {
            return [];
        }
    }
}
