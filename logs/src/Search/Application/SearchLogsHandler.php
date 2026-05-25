<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Search\Domain\SearchLogRepositoryInterface;
use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchPagination;
use App\Search\Domain\SearchResult;

/**
 * Orchestrateur applicatif Search.
 */
final readonly class SearchLogsHandler
{
    public function __construct(
        private SearchLogRepositoryInterface $repository,
    ) {
    }

    public function handle(
        SearchLogsRequest $request,
    ): SearchResult {
        $pagination = new SearchPagination(
            page: $request->getPage(),
            perPage: $request->getPerPage(),
        );

        $filters = new SearchFilters(
            fromDate: $request->getFromDate(),
            toDate: $request->getToDate(),
            level: $request->getLevel(),
            domain: $request->getDomain(),
            projectId: $request->getProjectId(),
            fingerprint: $request->getFingerprint(),
        );

        return $this->repository->search(
            $pagination,
            $filters,
        );
    }
}
