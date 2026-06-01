<?php

declare(strict_types=1);

namespace App\Tests\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardLogDetailsRepositoryInterface;
use App\Dashboard\Domain\DashboardLogDetailsView;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * Tests fonctionnels Dashboard détail log.
 */
final class DashboardLogDetailsControllerTest extends WebTestCase
{
    /**
     * But : éviter les sorties debug Symfony qui rendent les tests risky.
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SHELL_VERBOSITY'] = '-1';
        $_ENV['SHELL_VERBOSITY'] = '-1';
    }

    /**
     * But : afficher une vue détail stable avec les données principales.
     *
     * Entrée : GET /dashboard/logs/ext-42.
     * Résultat attendu : HTTP 200, HTML et champs clés visibles.
     */
    public function testItDisplaysLogDetails(): void
    {
        $repository = new FakeDashboardLogDetailsRepository(
            [
                'ext-42' => new DashboardLogDetailsView(
                    externalId: 'ext-42',
                    domainId: 1,
                    fingerprint: 'aabbccddeeff0011',
                    requestId: 'req-42',
                    level: 'error',
                    httpStatus: 500,
                    domain: 'billing',
                    uri: '/api/invoices',
                    method: 'POST',
                    userAgent: 'phpunit',
                    env: 'prod',
                    client: 'web',
                    message: 'Payment failed',
                    context: ['order_id' => 99],
                    extra: ['trace' => 'abc'],
                    ingestionWarnings: ['truncated context'],
                    createdAt: new \DateTimeImmutable('2026-05-25 12:00:00'),
                    clientDate: new \DateTimeImmutable('2026-05-25 11:59:59'),
                    ip: '127.0.0.1',
                ),
            ],
        );

        $client = static::createClient();

        static::getContainer()->set(
            DashboardLogDetailsRepositoryInterface::class,
            $repository,
        );

        $client->request('GET', '/dashboard/logs/ext-42');

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

        self::assertStringContainsString('Log ext-42', $content);
        self::assertStringContainsString('Payment failed', $content);
        self::assertStringContainsString('billing', $content);
        self::assertStringContainsString('req-42', $content);
        self::assertStringContainsString('aabbccddeeff0011', $content);
    }

    /**
     * But : retourner 404 quand un log est introuvable.
     *
     * Entrée : GET /dashboard/logs/missing.
     * Résultat attendu : HTTP 404.
     */
    public function testItReturns404WhenLogIsMissing(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            DashboardLogDetailsRepositoryInterface::class,
            new FakeDashboardLogDetailsRepository([]),
        );

        $client->request('GET', '/dashboard/logs/missing');

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $client->getResponse()->getStatusCode(),
        );
    }

    /**
     * But : vérifier qu'un détail est chargeable en fragment HTMX.
     *
     * Entrée : GET /dashboard/htmx/logs/ext-42/detail avec HX-Request.
     * Résultat attendu : HTTP 200 et contenu HTML partiel avec données clés.
     */
    public function testItReturnsDetailFragmentForHtmx(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            DashboardLogDetailsRepositoryInterface::class,
            new FakeDashboardLogDetailsRepository(
                [
                    'ext-42' => new DashboardLogDetailsView(
                        externalId: 'ext-42',
                        domainId: 1,
                        fingerprint: 'aabbccddeeff0011',
                        requestId: 'req-42',
                        level: 'error',
                        httpStatus: 500,
                        domain: 'billing',
                        uri: '/api/invoices',
                        method: 'POST',
                        userAgent: 'phpunit',
                        env: 'prod',
                        client: 'web',
                        message: 'Payment failed',
                        context: ['order_id' => 99],
                        extra: ['trace' => 'abc'],
                        ingestionWarnings: ['truncated context'],
                        createdAt: new \DateTimeImmutable('2026-05-25 12:00:00'),
                        clientDate: new \DateTimeImmutable('2026-05-25 11:59:59'),
                        ip: '127.0.0.1',
                    ),
                ],
            ),
        );

        $client->request(
            'GET',
            '/dashboard/htmx/logs/ext-42/detail',
            server: [
                'HTTP_HX_REQUEST' => 'true',
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue(
            $response->headers->contains(
                'content-type',
                'text/html; charset=UTF-8',
            ),
        );

        $content = $response->getContent() ?: '';

        self::assertStringContainsString('Log ext-42', $content);
        self::assertStringContainsString('Payment failed', $content);
        self::assertStringContainsString('Ouvrir la page complete', $content);
    }

    /**
     * But : vérifier qu'un fragment HTMX manquant retourne 404.
     */
    public function testItReturns404ForMissingDetailFragment(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            DashboardLogDetailsRepositoryInterface::class,
            new FakeDashboardLogDetailsRepository([]),
        );

        $client->request(
            'GET',
            '/dashboard/htmx/logs/missing/detail',
            server: [
                'HTTP_HX_REQUEST' => 'true',
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('Log introuvable.', $response->getContent() ?: '');
    }
}

/**
 * @internal
 */
final class FakeDashboardLogDetailsRepository implements DashboardLogDetailsRepositoryInterface
{
    /**
     * @param array<string, DashboardLogDetailsView> $logsByExternalId
     */
    public function __construct(
        private readonly array $logsByExternalId,
    ) {
    }

    public function findByExternalId(
        string $externalId,
    ): ?DashboardLogDetailsView {
        return $this->logsByExternalId[$externalId] ?? null;
    }
}
