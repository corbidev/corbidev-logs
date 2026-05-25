<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure;

use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchPagination;
use App\Search\Infrastructure\DoctrineSearchLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

/**
 * Tests du repository Doctrine Search.
 */
final class DoctrineSearchLogRepositoryTest extends TestCase
{
    /**
    * But : Vérifier que search() combine les filtres et applique LIMIT/OFFSET explicites.
     *
    * Entrée : pagination(page=2, perPage=10) + filtres période/level/domain/project/fingerprint.
    * Résultat attendu : SQL contient WHERE combiné + LIMIT/OFFSET, résultat paginé stable.
     */
    public function testSearchUsesExplicitLimitAndOffset(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::callback(
                    static function (string $sql): bool {
                        return str_contains($sql, 'WHERE')
                            && str_contains($sql, 'created_at >= :from_date')
                            && str_contains($sql, 'created_at <= :to_date')
                            && str_contains($sql, 'level = :level')
                            && str_contains($sql, 'domain = :domain')
                            && str_contains($sql, 'project_id = :project_id')
                            && str_contains($sql, 'fingerprint = :fingerprint');
                    },
                ),
                [
                    'from_date' => '2026-05-01 00:00:00',
                    'to_date' => '2026-05-31 23:59:59',
                    'level' => 'error',
                    'domain' => 'billing',
                    'project_id' => 1,
                    'fingerprint' => 'abcdef1234567890',
                ],
            )
            ->willReturn(12);

        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(
                    static function (string $sql): bool {
                        return str_contains($sql, 'LIMIT :limit')
                            && str_contains($sql, 'OFFSET :offset');
                    },
                ),
                [
                    'from_date' => '2026-05-01 00:00:00',
                    'to_date' => '2026-05-31 23:59:59',
                    'level' => 'error',
                    'domain' => 'billing',
                    'project_id' => 1,
                    'fingerprint' => 'abcdef1234567890',
                    'limit' => 10,
                    'offset' => 10,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                    'offset' => ParameterType::INTEGER,
                ],
            )
            ->willReturn([
                [
                    'id' => 44,
                    'external_id' => '3f5e0a61-0000-4000-8000-111111111111',
                    'project_id' => 1,
                    'level' => 'error',
                    'domain' => 'billing',
                    'message' => 'Payment failed',
                    'created_at' => '2026-05-25 12:00:00',
                ],
            ]);

        $repository = new DoctrineSearchLogRepository(
            $connection,
        );

        $result = $repository->search(
            new SearchPagination(
                page: 2,
                perPage: 10,
            ),
            new SearchFilters(
                fromDate: new \DateTimeImmutable('2026-05-01 00:00:00'),
                toDate: new \DateTimeImmutable('2026-05-31 23:59:59'),
                level: 'error',
                domain: 'billing',
                projectId: 1,
                fingerprint: 'abcdef1234567890',
            ),
        );

        self::assertSame(2, $result->getPage());
        self::assertSame(10, $result->getPerPage());
        self::assertSame(12, $result->getTotalCount());
        self::assertCount(1, $result->getItems());
    }

    /**
     * But : Vérifier que search() remonte une erreur technique explicite en cas d'échec SQL.
     *
     * Entrée : fetchOne() lève RuntimeException('db unavailable').
     * Résultat attendu : RuntimeException préfixée 'Search query failed:'.
     */
    public function testSearchThrowsExplicitTechnicalErrorOnSqlFailure(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willThrowException(
                new \RuntimeException('db unavailable'),
            );

        $repository = new DoctrineSearchLogRepository(
            $connection,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Search query failed: db unavailable');

        $repository->search(
            new SearchPagination(
                page: 1,
                perPage: 10,
            ),
            new SearchFilters(),
        );
    }
}
