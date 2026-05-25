<?php

declare(strict_types=1);

namespace App\Project\Infrastructure;

use App\Project\Domain\Project;
use App\Project\Domain\ProjectRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * Repository DBAL des projets.
 */
final readonly class DoctrineProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findById(int $id): ?Project
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(
                'Project id must be positive.',
            );
        }

        return $this->findOne(
            'SELECT id, slug, name, retention_days, is_active, created_at, updated_at FROM projects WHERE id = :id LIMIT 1',
            [
                'id' => $id,
            ],
        );
    }

    public function findBySlug(string $slug): ?Project
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new \InvalidArgumentException(
                'Project slug cannot be empty.',
            );
        }

        return $this->findOne(
            'SELECT id, slug, name, retention_days, is_active, created_at, updated_at FROM projects WHERE slug = :slug LIMIT 1',
            [
                'slug' => mb_strtolower($slug),
            ],
        );
    }

    /**
     * @param array<string, scalar> $params
     */
    private function findOne(
        string $sql,
        array $params,
    ): ?Project {
        try {
            $row = $this->connection->fetchAssociative(
                $sql,
                $params,
            );

            if (!is_array($row)) {
                return null;
            }

            return $this->mapRowToProject($row);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Project query failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToProject(array $row): Project
    {
        try {
            return new Project(
                id: (int) ($row['id'] ?? 0),
                slug: (string) ($row['slug'] ?? ''),
                name: (string) ($row['name'] ?? ''),
                retentionDays: (int) ($row['retention_days'] ?? 0),
                isActive: (bool) ($row['is_active'] ?? false),
                createdAt: new \DateTimeImmutable(
                    (string) ($row['created_at'] ?? 'now'),
                ),
                updatedAt: new \DateTimeImmutable(
                    (string) ($row['updated_at'] ?? 'now'),
                ),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Project mapping failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
