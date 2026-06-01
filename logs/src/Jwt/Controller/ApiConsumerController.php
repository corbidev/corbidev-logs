<?php

declare(strict_types=1);

namespace App\Jwt\Controller;

use App\Jwt\Entity\ApiConsumer;
use App\Jwt\Repository\ApiConsumerRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/consumers')]
class ApiConsumerController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    private function checkAccess(Request $request): void
    {
        if (!$request->getSession()->get('admin')) {
            throw $this->createAccessDeniedException('Access denied');
        }
    }

    #[Route('', name: 'admin_consumers', methods: ['GET'])]
    public function index(Request $request, ApiConsumerRepository $repo): Response
    {
        $this->checkAccess($request);

        $domains = $this->fetchDomains();
        $domainNamesById = [];

        foreach ($domains as $domain) {
            $domainNamesById[(int) $domain['id']] = sprintf('%s (%s)', (string) $domain['name'], (string) $domain['slug']);
        }

        return $this->render('admin/consumer/index.html.twig', [
            'consumers' => $repo->findAll(),
            'domainNamesById' => $domainNamesById,
        ]);
    }

    #[Route('/create', name: 'admin_consumers_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $this->checkAccess($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_consumer_create', (string) $request->request->get('_token'))) {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Session invalide, merci de reessayer.',
                    'identifier' => (string) $request->request->get('identifier', ''),
                    'domain_id' => (string) $request->request->get('domain_id', ''),
                    'domains' => $this->fetchDomains(),
                ]);
            }

            $identifier = trim((string) $request->request->get('identifier', ''));
            $domainId = (int) $request->request->get('domain_id', 0);
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if ($identifier === '' || $password === '' || $domainId <= 0) {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Domaine, identifiant et mot de passe sont requis.',
                    'identifier' => $identifier,
                    'domain_id' => $domainId > 0 ? (string) $domainId : '',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            if (!$this->domainExists($domainId)) {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Le domaine selectionne est introuvable.',
                    'identifier' => $identifier,
                    'domain_id' => $domainId > 0 ? (string) $domainId : '',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            if ($password !== $passwordConfirm) {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Les mots de passe ne correspondent pas.',
                    'identifier' => $identifier,
                    'domain_id' => $domainId > 0 ? (string) $domainId : '',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            $consumer = new ApiConsumer(
                $identifier,
                password_hash($password, PASSWORD_BCRYPT),
                $domainId,
            );

            $em->persist($consumer);
            $em->flush();

            return $this->redirectToRoute('admin_consumers');
        }

        return $this->render('admin/consumer/create.html.twig', [
            'identifier' => '',
            'domain_id' => '',
            'domains' => $this->fetchDomains(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_consumers_edit', methods: ['GET', 'POST'])]
    public function edit(ApiConsumer $consumer, Request $request, EntityManagerInterface $em): Response
    {
        $this->checkAccess($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_consumer_edit_' . $consumer->getId(), (string) $request->request->get('_token'))) {
                return $this->render('admin/consumer/edit.html.twig', [
                    'consumer' => $consumer,
                    'error' => 'Session invalide, merci de reessayer.',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            $domainId = (int) $request->request->get('domain_id', 0);
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if ($domainId <= 0 || !$this->domainExists($domainId)) {
                return $this->render('admin/consumer/edit.html.twig', [
                    'consumer' => $consumer,
                    'error' => 'Le domaine selectionne est introuvable.',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            if (($password !== '' || $passwordConfirm !== '') && $password !== $passwordConfirm) {
                return $this->render('admin/consumer/edit.html.twig', [
                    'consumer' => $consumer,
                    'error' => 'Les mots de passe ne correspondent pas.',
                    'domains' => $this->fetchDomains(),
                ]);
            }

            if ($password !== '') {
                $consumer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
            }

            $consumer->setDomainId($domainId);
            $consumer->setActive((bool)$request->request->get('active'));

            $em->flush();

            return $this->redirectToRoute('admin_consumers');
        }

        return $this->render('admin/consumer/edit.html.twig', [
            'consumer' => $consumer,
            'error' => null,
            'domains' => $this->fetchDomains(),
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_consumers_toggle', methods: ['POST'])]
    public function toggle(ApiConsumer $consumer, Request $request, EntityManagerInterface $em): Response
    {
        $this->checkAccess($request);

        if (!$this->isCsrfTokenValid('admin_consumer_toggle_' . $consumer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $consumer->setActive(!$consumer->isActive());
        $em->flush();

        return $this->redirectToRoute('admin_consumers');
    }

    #[Route('/{id}/delete', name: 'admin_consumers_delete', methods: ['POST'])]
    public function delete(ApiConsumer $consumer, Request $request, EntityManagerInterface $em): Response
    {
        $this->checkAccess($request);

        if (!$this->isCsrfTokenValid('admin_consumer_delete_' . $consumer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($consumer);
        $em->flush();

        return $this->redirectToRoute('admin_consumers');
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    private function fetchDomains(): array
    {
        /** @var array<int, array{id: int, slug: string, name: string}> $domains */
        $domains = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name FROM domains ORDER BY name ASC',
        );

        return $domains;
    }

    private function domainExists(int $domainId): bool
    {
        $exists = $this->connection->fetchOne(
            'SELECT id FROM domains WHERE id = :id LIMIT 1',
            ['id' => $domainId],
        );

        return $exists !== false;
    }
}
