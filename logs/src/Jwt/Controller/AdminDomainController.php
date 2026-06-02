<?php

declare(strict_types=1);

namespace App\Jwt\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/domains')]
final class AdminDomainController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('', name: 'admin_domains', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->checkAccess($request);

        return $this->render('admin/domain/index.html.twig', [
            'domains' => $this->fetchDomains(),
        ]);
    }

    #[Route('/create', name: 'admin_domains_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->checkAccess($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_domain_create', (string) $request->request->get('_token'))) {
                return $this->render('admin/domain/create.html.twig', [
                    'error' => 'Session invalide, merci de reessayer.',
                    'form' => $this->buildFormValues($request),
                ]);
            }

            $slug = $this->sanitizeSlug((string) $request->request->get('slug', ''));
            $name = trim((string) $request->request->get('name', ''));
            $retentionDays = max(1, (int) $request->request->get('retention_days', 30));
            $isActive = (bool) $request->request->get('is_active');

            if ($slug === '' || $name === '') {
                return $this->render('admin/domain/create.html.twig', [
                    'error' => 'Le slug et le nom sont obligatoires.',
                    'form' => $this->buildFormValues($request),
                ]);
            }

            try {
                $this->connection->beginTransaction();

                $this->connection->insert(
                    'domains',
                    [
                        'slug' => $slug,
                        'name' => $name,
                        'retention_days' => $retentionDays,
                        'is_active' => $isActive ? 1 : 0,
                    ],
                );

                $insertedId = (int) $this->connection->lastInsertId();

                $this->connection->insert(
                    'projects',
                    [
                        'id' => $insertedId,
                        'slug' => $slug,
                        'name' => $name,
                        'retention_days' => $retentionDays,
                        'is_active' => $isActive ? 1 : 0,
                    ],
                );

                $this->connection->commit();
            } catch (\Throwable $exception) {
                $this->connection->rollBack();

                return $this->render('admin/domain/create.html.twig', [
                    'error' => sprintf('Creation impossible: %s', $exception->getMessage()),
                    'form' => $this->buildFormValues($request),
                ]);
            }

            return $this->redirectToRoute('admin_domains');
        }

        return $this->render('admin/domain/create.html.twig', [
            'error' => null,
            'form' => [
                'slug' => '',
                'name' => '',
                'retention_days' => '30',
                'is_active' => true,
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_domains_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $this->checkAccess($request);

        $domain = $this->fetchDomainById($id);

        if ($domain === null) {
            throw $this->createNotFoundException('Domaine introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_domain_edit_' . $id, (string) $request->request->get('_token'))) {
                return $this->render('admin/domain/edit.html.twig', [
                    'error' => 'Session invalide, merci de reessayer.',
                    'domain' => $domain,
                ]);
            }

            $slug = $this->sanitizeSlug((string) $request->request->get('slug', ''));
            $name = trim((string) $request->request->get('name', ''));
            $retentionDays = max(1, (int) $request->request->get('retention_days', 30));
            $isActive = (bool) $request->request->get('is_active');

            if ($slug === '' || $name === '') {
                $domain['slug'] = $slug;
                $domain['name'] = $name;
                $domain['retention_days'] = $retentionDays;
                $domain['is_active'] = $isActive;

                return $this->render('admin/domain/edit.html.twig', [
                    'error' => 'Le slug et le nom sont obligatoires.',
                    'domain' => $domain,
                ]);
            }

            try {
                $this->connection->beginTransaction();

                $this->connection->update(
                    'domains',
                    [
                        'slug' => $slug,
                        'name' => $name,
                        'retention_days' => $retentionDays,
                        'is_active' => $isActive ? 1 : 0,
                    ],
                    [
                        'id' => $id,
                    ],
                );

                $this->connection->update(
                    'projects',
                    [
                        'slug' => $slug,
                        'name' => $name,
                        'retention_days' => $retentionDays,
                        'is_active' => $isActive ? 1 : 0,
                    ],
                    [
                        'id' => $id,
                    ],
                );

                $this->connection->commit();
            } catch (\Throwable $exception) {
                $this->connection->rollBack();

                $domain['slug'] = $slug;
                $domain['name'] = $name;
                $domain['retention_days'] = $retentionDays;
                $domain['is_active'] = $isActive;

                return $this->render('admin/domain/edit.html.twig', [
                    'error' => sprintf('Mise a jour impossible: %s', $exception->getMessage()),
                    'domain' => $domain,
                ]);
            }

            return $this->redirectToRoute('admin_domains');
        }

        return $this->render('admin/domain/edit.html.twig', [
            'error' => null,
            'domain' => $domain,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_domains_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request): RedirectResponse
    {
        $this->checkAccess($request);

        if (!$this->isCsrfTokenValid('admin_domain_toggle_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement(
                'UPDATE domains SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id',
                [
                    'id' => $id,
                ],
            );

            $this->connection->executeStatement(
                'UPDATE projects SET is_active = (SELECT d.is_active FROM domains d WHERE d.id = :id) WHERE id = :id',
                [
                    'id' => $id,
                ],
            );

            $this->connection->commit();
        } catch (\Throwable) {
            $this->connection->rollBack();

            throw $this->createAccessDeniedException('Toggle impossible.');
        }

        return $this->redirectToRoute('admin_domains');
    }

    private function checkAccess(Request $request): void
    {
        if (!$request->getSession()->get('admin')) {
            throw $this->createAccessDeniedException('Access denied');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDomains(): array
    {
        /** @var array<int, array<string, mixed>> $domains */
        $domains = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name, retention_days, is_active, created_at, updated_at FROM domains ORDER BY name ASC',
        );

        return $domains;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDomainById(int $id): ?array
    {
        /** @var array<string, mixed>|false $domain */
        $domain = $this->connection->fetchAssociative(
            'SELECT id, slug, name, retention_days, is_active, created_at, updated_at FROM domains WHERE id = :id LIMIT 1',
            [
                'id' => $id,
            ],
        );

        if ($domain === false) {
            return null;
        }

        return $domain;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormValues(Request $request): array
    {
        return [
            'slug' => $this->sanitizeSlug((string) $request->request->get('slug', '')),
            'name' => trim((string) $request->request->get('name', '')),
            'retention_days' => (string) max(1, (int) $request->request->get('retention_days', 30)),
            'is_active' => (bool) $request->request->get('is_active', true),
        ];
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = trim(mb_strtolower($slug));

        if ($slug === '') {
            return '';
        }

        return (string) preg_replace('/[^a-z0-9._-]+/', '-', $slug);
    }
}
