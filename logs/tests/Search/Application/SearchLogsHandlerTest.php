<?php

declare(strict_types=1);

namespace App\Tests\Search\Application;

use App\Search\Application\SearchLogsHandler;
use App\Search\Application\SearchLogsRequest;
use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchLogRepositoryInterface;
use App\Search\Domain\SearchPagination;
use App\Search\Domain\SearchResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests du handler Search.
 */
final class SearchLogsHandlerTest extends TestCase
{
    /**
     * But : Vérifier que le handler impose une pagination et délègue au repository.
     *
     * Entrée : request(page=2, perPage=25).
    * Résultat attendu : repository->search() appelé avec pagination équivalente et filtres transmis.
     */
    public function testHandleBuildsPaginationAndDelegatesToRepository(): void
    {
        $repository = $this->createMock(
            SearchLogRepositoryInterface::class,
        );

        $repository
            ->expects(self::once())
            ->method('search')
            ->with(
                self::callback(
                    static function (SearchPagination $pagination): bool {
                        return $pagination->getPage() === 2
                            && $pagination->getPerPage() === 25
                            && $pagination->getOffset() === 25;
                    },
                ),
                self::callback(
                    static function (SearchFilters $filters): bool {
                        return $filters->getProjectId() === 3
                            && $filters->getLevel() === 'error'
                            && $filters->getDomain() === 'billing'
                            && $filters->getFingerprint() === 'abcdef1234567890';
                    },
                ),
            )
            ->willReturn(
                new SearchResult(
                    items: [],
                    page: 2,
                    perPage: 25,
                    totalCount: 0,
                ),
            );

        $handler = new SearchLogsHandler(
            $repository,
        );

        $result = $handler->handle(
            new SearchLogsRequest(
                page: 2,
                perPage: 25,
                fromDate: new \DateTimeImmutable('2026-05-01 00:00:00'),
                toDate: new \DateTimeImmutable('2026-05-31 23:59:59'),
                level: 'error',
                domain: 'billing',
                projectId: 3,
                fingerprint: 'abcdef1234567890',
            ),
        );

        self::assertSame(2, $result->getPage());
        self::assertSame(25, $result->getPerPage());
    }
}
