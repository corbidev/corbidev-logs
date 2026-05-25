<?php

declare(strict_types=1);

namespace App\Search\Infrastructure;

use App\Search\Domain\SearchLogEntryView;
use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchLogRepositoryInterface;
use App\Search\Domain\SearchPagination;
use App\Search\Domain\SearchResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Repository DBAL de lecture bornée.
 */
final readonly class DoctrineSearchLogRepository implements SearchLogRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function search(
        SearchPagination $pagination,
        SearchFilters $filters,
    ): SearchResult
    {
        try {
            [$whereSql, $params] = $this->buildWhereClause(
                $filters,
            );

            $totalCount = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM logs ' . $whereSql,
                $params,
            );

            $params['limit'] = $pagination->getPerPage();
            $params['offset'] = $pagination->getOffset();

            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, external_id, project_id, level, domain, message, created_at FROM logs '
                . $whereSql
                . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset',
                $params,
                [
                    'limit' => ParameterType::INTEGER,
                    'offset' => ParameterType::INTEGER,
                ],
            );

            $items = [];

            foreach ($rows as $row) {
                $items[] = $this->mapRowToView($row);
            }

            return new SearchResult(
                items: $items,
                page: $pagination->getPage(),
                perPage: $pagination->getPerPage(),
                totalCount: $totalCount,
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Search query failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @return array{0:string,1:array<string,scalar>}
     */
    private function buildWhereClause(
        SearchFilters $filters,
    ): array {
        $conditions = [];

        /** @var array<string,scalar> $params */
        $params = [];

        if ($filters->getFromDate() !== null) {
            $conditions[] = 'created_at >= :from_date';
            $params['from_date'] = $filters
                ->getFromDate()
                ?->format('Y-m-d H:i:s') ?? '';
        }

        if ($filters->getToDate() !== null) {
            $conditions[] = 'created_at <= :to_date';
            $params['to_date'] = $filters
                ->getToDate()
                ?->format('Y-m-d H:i:s') ?? '';
        }

        if ($filters->getLevel() !== null) {
            $conditions[] = 'level = :level';
            $params['level'] = $filters->getLevel() ?? '';
        }

        if ($filters->getDomain() !== null) {
            $conditions[] = 'domain = :domain';
            $params['domain'] = $filters->getDomain() ?? '';
        }

        if ($filters->getProjectId() !== null) {
            $conditions[] = 'project_id = :project_id';
            $params['project_id'] = $filters->getProjectId() ?? 0;
        }

        if ($filters->getFingerprint() !== null) {
            $conditions[] = 'fingerprint = :fingerprint';
            $params['fingerprint'] = $filters->getFingerprint() ?? '';
        }

        if ($conditions === []) {
            return ['', $params];
        }

        return [
            'WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToView(array $row): SearchLogEntryView
    {
        return new SearchLogEntryView(
            id: (int) ($row['id'] ?? 0),
            externalId: (string) ($row['external_id'] ?? ''),
            projectId: (int) ($row['project_id'] ?? 0),
            level: (string) ($row['level'] ?? ''),
            domain: (string) ($row['domain'] ?? ''),
            message: (string) ($row['message'] ?? ''),
            createdAt: new \DateTimeImmutable(
                (string) ($row['created_at'] ?? 'now'),
            ),
        );
    }
}
