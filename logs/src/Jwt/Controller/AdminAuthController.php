<?php

declare(strict_types=1);

namespace App\Jwt\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private readonly string $adminUser,
        private readonly string $adminPasswordHash,
    ) {}

    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_login', (string) $request->request->get('_token'))) {
                return $this->render('admin/login.html.twig', [
                    'error' => 'Session invalide, merci de reessayer.',
                ]);
            }

            $user = $request->request->get('user');
            $password = $request->request->get('password');
            $normalizedHash = $this->resolveAdminPasswordHash($this->adminPasswordHash);
            $isSupportedHash = $normalizedHash !== null;

            if (
                is_string($user)
                && hash_equals($this->adminUser, $user)
                && is_string($password)
                && $isSupportedHash
                && password_verify($password, (string) $normalizedHash)
            ) {
                $request->getSession()->set('admin', true);

                return $this->redirectToRoute('admin_domains');
            }

            return $this->render('admin/login.html.twig', [
                'error' => 'Identifiants invalides.',
            ]);
        }

        return $this->render('admin/login.html.twig');
    }

    #[Route('/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_logout', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $request->getSession()->remove('admin');

        return $this->redirect('/admin/login');
    }

    private function resolveAdminPasswordHash(string $rawHash): ?string
    {
        $trimmed = trim($rawHash, " \t\n\r\0\x0B'\"");

        if (password_get_info($trimmed)['algo'] !== null) {
            return $trimmed;
        }

        if (preg_match('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/', $trimmed, $matches) !== 1) {
            return null;
        }

        $candidate = $matches[0];

        if (password_get_info($candidate)['algo'] === null) {
            return null;
        }

        return $candidate;
    }
}
