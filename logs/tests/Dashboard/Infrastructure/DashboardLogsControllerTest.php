<?php

declare(strict_types=1);

namespace App\Tests\Dashboard\Infrastructure;

use App\Search\Domain\SearchFilters;
use App\Search\Domain\SearchLogEntryView;
use App\Search\Domain\SearchLogRepositoryInterface;
use App\Search\Domain\SearchPagination;
use App\Search\Domain\SearchResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * Tests fonctionnels Dashboard logs.
 */
final class DashboardLogsControllerTest extends WebTestCase
{
    /**
     * But : Éviter les sorties debug Symfony qui rendent les tests risky.
     *
     * Entrée : initialisation de chaque test.
     * Résultat attendu : SHELL_VERBOSITY positionné à -1.
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';
    }

    /**
     * But : Vérifier que la liste Dashboard affiche une pagination fonctionnelle.
     *
     * Entrée : GET /dashboard/logs?page=2&per_page=2.
     * Résultat attendu : HTTP 200, HTML, page 2 affichée et navigation suivante visible.
     */
    public function testItDisplaysPaginatedLogsList(): void
    {
        $client = $this->createClientWithFakeSearchRepository();

        $crawler = $client->request(
            'GET',
            '/dashboard/logs?page=2&per_page=2',
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'text/html; charset=UTF-8',
            ),
        );

        self::assertStringContainsString(
            'Dashboard Logs',
            $response->getContent() ?: '',
        );

        self::assertStringContainsString(
            'Page 2 / 3',
            $response->getContent() ?: '',
        );

        self::assertGreaterThan(
            0,
            $crawler->filter('a:contains("Page suivante")')->count(),
        );

        self::assertStringContainsString(
            'ext-3',
            $response->getContent() ?: '',
        );
    }

    /**
     * But : Vérifier que per_page est borné côté Dashboard.
     *
     * Entrée : GET /dashboard/logs?per_page=9999.
     * Résultat attendu : repository appelé avec perPage=200 max.
     */
    public function testItClampsPerPageToPreventMassiveLoad(): void
    {
        $repository = new FakeDashboardSearchRepository();

        $client = static::createClient();
        static::getContainer()->set(
            SearchLogRepositoryInterface::class,
            $repository,
        );

        $client->request(
            'GET',
            '/dashboard/logs?per_page=9999',
        );

        self::assertSame(200, $repository->lastPerPage);
    }

    /**
     * But : Vérifier que la route fragment HTMX retourne un HTML partiel exploitable.
     *
     * Entrée : GET /dashboard/htmx/logs/list?page=1&per_page=2 avec header HX-Request.
     * Résultat attendu : HTTP 200, fragment contenant la région de liste et les logs.
     */
    public function testItReturnsHtmlFragmentForHtmxListRoute(): void
    {
        $client = $this->createClientWithFakeSearchRepository();

        $client->request(
            'GET',
            '/dashboard/htmx/logs/list?page=1&per_page=2',
            server: [
                'HTTP_HX_REQUEST' => 'true',
            ],
        );

        $response = $client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
        );

        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'text/html; charset=UTF-8',
            ),
        );

        $content = $response->getContent() ?: '';

        self::assertStringContainsString('id="logs-list-region"', $content);
        self::assertStringContainsString('Page 1 / 3', $content);
        self::assertStringContainsString('ext-1', $content);
    }

    private function createClientWithFakeSearchRepository(): KernelBrowser
    {
        $client = static::createClient();

        static::getContainer()->set(
            SearchLogRepositoryInterface::class,
            new FakeDashboardSearchRepository(),
        );

        return $client;
    }
}

final class FakeDashboardSearchRepository implements SearchLogRepositoryInterface
{
    public int $lastPerPage = 0;

    public function search(
        SearchPagination $pagination,
        SearchFilters $filters,
    ): SearchResult {
        $this->lastPerPage = $pagination->getPerPage();

        $totalCount = 5;
        $start = (($pagination->getPage() - 1) * $pagination->getPerPage()) + 1;

        $items = [];

        for ($i = 0; $i < $pagination->getPerPage(); ++$i) {
            $index = $start + $i;

            if ($index > $totalCount) {
                break;
            }

            $items[] = new SearchLogEntryView(
                id: $index,
                externalId: 'ext-' . $index,
                projectId: 1,
                level: 'error',
                domain: 'billing',
                httpStatus: 500,
                uri: '/api/invoices',
                requestId: 'req-' . $index,
                fingerprint: 'aabbccddeeff00' . str_pad((string) ($index % 100), 2, '0', STR_PAD_LEFT),
                message: 'Message ' . $index,
                createdAt: new \DateTimeImmutable('2026-05-25 12:00:00'),
            );
        }

        return new SearchResult(
            items: $items,
            page: $pagination->getPage(),
            perPage: $pagination->getPerPage(),
            totalCount: $totalCount,
        );
    }
}
