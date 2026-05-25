<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardLogDetailsRepositoryInterface;
use App\Dashboard\Domain\DashboardLogDetailsView;
use Doctrine\DBAL\Connection;

/**
 * Lecture SQL d'un log détaillé pour le Dashboard.
 */
final readonly class DoctrineDashboardLogDetailsRepository implements DashboardLogDetailsRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findByExternalId(
        string $externalId,
    ): ?DashboardLogDetailsView {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT external_id, project_id, fingerprint, request_id, level, http_status, domain, uri, method, user_agent, env, client, message, context_json, extra_json, ingestion_warnings_json, created_at, client_date, ip FROM logs WHERE external_id = :external_id LIMIT 1',
                ['external_id' => $externalId],
            );

            if (!is_array($row)) {
                return null;
            }

            return $this->mapRowToView($row);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Dashboard log details query failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToView(array $row): DashboardLogDetailsView
    {
        return new DashboardLogDetailsView(
            externalId: (string) ($row['external_id'] ?? ''),
            projectId: (int) ($row['project_id'] ?? 0),
            fingerprint: (string) ($row['fingerprint'] ?? ''),
            requestId: (string) ($row['request_id'] ?? ''),
            level: (string) ($row['level'] ?? ''),
            httpStatus: (int) ($row['http_status'] ?? 0),
            domain: (string) ($row['domain'] ?? ''),
            uri: (string) ($row['uri'] ?? ''),
            method: $this->nullableString($row['method'] ?? null),
            userAgent: $this->nullableString($row['user_agent'] ?? null),
            env: (string) ($row['env'] ?? ''),
            client: (string) ($row['client'] ?? ''),
            message: (string) ($row['message'] ?? ''),
            context: $this->decodeJsonObject($row['context_json'] ?? '{}'),
            extra: $this->decodeJsonObject($row['extra_json'] ?? '{}'),
            ingestionWarnings: $this->decodeJsonStringList($row['ingestion_warnings_json'] ?? '[]'),
            createdAt: new \DateTimeImmutable(
                (string) ($row['created_at'] ?? 'now'),
            ),
            clientDate: $this->nullableDateTimeImmutable($row['client_date'] ?? null),
            ip: $this->nullableString($row['ip'] ?? null),
        );
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private function decodeJsonStringList(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (!is_array($decoded)) {
                return [];
            }

            $result = [];

            foreach ($decoded as $item) {
                if (is_string($item)) {
                    $result[] = $item;
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param mixed $value
     */
    private function nullableDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param mixed $value
     */
    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}