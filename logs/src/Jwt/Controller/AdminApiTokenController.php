<?php

declare(strict_types=1);

namespace App\Jwt\Controller;

use App\ApiToken\Application\CreateApiTokenHandler;
use App\ApiToken\Application\CreateApiTokenRequest;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tokens')]
final class AdminApiTokenController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CreateApiTokenHandler $createApiTokenHandler,
    ) {
    }

    #[Route('', name: 'admin_tokens', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->checkAccess($request);

        return $this->render('admin/token/index.html.twig', [
            'projects' => $this->fetchProjects(),
            'tokens' => $this->fetchTokens(),
            'form' => [
                'project_id' => '',
                'label' => '',
                'expires_at' => '',
            ],
            'error' => null,
        ]);
    }

    #[Route('/create', name: 'admin_tokens_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->checkAccess($request);

        if (!$this->isCsrfTokenValid('admin_token_create', (string) $request->request->get('_token'))) {
            return $this->renderFormWithError(
                'Session invalide, merci de reessayer.',
                (int) $request->request->get('project_id', 0),
                trim((string) $request->request->get('label', '')),
                trim((string) $request->request->get('expires_at', '')),
            );
        }

        $projectId = (int) $request->request->get('project_id', 0);
        $label = trim((string) $request->request->get('label', ''));
        $expiresAtRaw = trim((string) $request->request->get('expires_at', ''));

        if ($projectId <= 0 || $label === '') {
            return $this->renderFormWithError(
                'Le projet et le libelle sont obligatoires.',
                $projectId,
                $label,
                $expiresAtRaw,
            );
        }

        $expiresAt = null;

        if ($expiresAtRaw !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtRaw);
            } catch (\Throwable) {
                return $this->renderFormWithError(
                    "La date d'expiration est invalide.",
                    $projectId,
                    $label,
                    $expiresAtRaw,
                );
            }
        }

        try {
            $result = $this->createApiTokenHandler->handle(
                new CreateApiTokenRequest(
                    projectId: $projectId,
                    label: $label,
                    expiresAt: $expiresAt,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->renderFormWithError(
                sprintf('Creation du token impossible: %s', $exception->getMessage()),
                $projectId,
                $label,
                $expiresAtRaw,
            );
        }

        $this->addFlash('token_created', [
            'plain_token' => $result->getPlainToken(),
            'token_prefix' => $result->getTokenPrefix(),
        ]);

        return $this->redirectToRoute('admin_tokens');
    }

    #[Route('/{id}/revoke', name: 'admin_tokens_revoke', methods: ['POST'])]
    public function revoke(int $id, Request $request): RedirectResponse
    {
        $this->checkAccess($request);

        if (!$this->isCsrfTokenValid('admin_token_revoke_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $affectedRows = $this->connection->executeStatement(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL',
            [
                'id' => $id,
            ],
        );

        if ($affectedRows > 0) {
            $this->addFlash('success', 'Token revoque avec succes.');
        } else {
            $this->addFlash('error', 'Token introuvable ou deja revoque.');
        }

        return $this->redirectToRoute('admin_tokens');
    }

    private function checkAccess(Request $request): void
    {
        if (!$request->getSession()->get('admin')) {
            throw $this->createAccessDeniedException('Access denied');
        }
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    private function fetchProjects(): array
    {
        /** @var array<int, array{id: int, slug: string, name: string}> $projects */
        $projects = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name FROM projects ORDER BY name ASC',
        );

        return $projects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTokens(): array
    {
        /** @var array<int, array<string, mixed>> $tokens */
        $tokens = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT
    t.id,
    t.project_id,
    p.slug,
    p.name AS project_name,
    t.label,
    t.token_prefix,
    t.created_at,
    t.expires_at,
    t.revoked_at
FROM api_tokens t
INNER JOIN projects p ON p.id = t.project_id
ORDER BY t.created_at DESC
SQL,
        );

        return $tokens;
    }

    private function renderFormWithError(
        string $error,
        int $projectId,
        string $label,
        string $expiresAt,
    ): Response {
        return $this->render('admin/token/index.html.twig', [
            'projects' => $this->fetchProjects(),
            'tokens' => $this->fetchTokens(),
            'form' => [
                'project_id' => $projectId > 0 ? (string) $projectId : '',
                'label' => $label,
                'expires_at' => $expiresAt,
            ],
            'error' => $error,
        ]);
    }
}
