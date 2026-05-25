<?php

declare(strict_types=1);

namespace App\Jwt\Controller;

use App\Jwt\Entity\ApiConsumer;
use App\Jwt\Repository\ApiConsumerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/consumers')]
class ApiConsumerController extends AbstractController
{
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

        return $this->render('admin/consumer/index.html.twig', [
            'consumers' => $repo->findAll(),
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
                ]);
            }

            $identifier = trim((string) $request->request->get('identifier', ''));
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if ($identifier === '' || $password === '') {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Identifiant et mot de passe sont requis.',
                    'identifier' => $identifier,
                ]);
            }

            if ($password !== $passwordConfirm) {
                return $this->render('admin/consumer/create.html.twig', [
                    'error' => 'Les mots de passe ne correspondent pas.',
                    'identifier' => $identifier,
                ]);
            }

            $consumer = new ApiConsumer(
                $identifier,
                password_hash($password, PASSWORD_BCRYPT)
            );

            $em->persist($consumer);
            $em->flush();

            return $this->redirectToRoute('admin_consumers');
        }

        return $this->render('admin/consumer/create.html.twig', [
            'identifier' => '',
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
                ]);
            }

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if (($password !== '' || $passwordConfirm !== '') && $password !== $passwordConfirm) {
                return $this->render('admin/consumer/edit.html.twig', [
                    'consumer' => $consumer,
                    'error' => 'Les mots de passe ne correspondent pas.',
                ]);
            }

            if ($password !== '') {
                $consumer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
            }

            $consumer->setActive((bool)$request->request->get('active'));

            $em->flush();

            return $this->redirectToRoute('admin_consumers');
        }

        return $this->render('admin/consumer/edit.html.twig', [
            'consumer' => $consumer,
            'error' => null,
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
}