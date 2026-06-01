<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Twig\Environment;

/**
 * Standardise les erreurs HTTP:
 * - HTML pour les appels navigateur
 * - JSON pour les appels API
 */
final class ExceptionResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly KernelInterface $kernel,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 200],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $throwable = $event->getThrowable();
        $statusCode = $this->resolveStatusCode($throwable);
        $statusText = $this->resolveStatusText($statusCode);
        $message = $this->resolvePublicMessage($statusCode, $throwable->getMessage());

        if ($this->isApiRequest($request)) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'success' => false,
                        'error' => 'http_error',
                        'status' => $statusCode,
                        'title' => $statusText,
                        'message' => $message,
                        'path' => $request->getPathInfo(),
                    ],
                    $statusCode,
                ),
            );

            return;
        }

        if ($this->shouldRedirectToLogin($request, $throwable, $statusCode)) {
            $loginUrl = $this->urlGenerator->generate('admin_login');

            $event->setResponse(new RedirectResponse($loginUrl));

            return;
        }

        $template = $statusCode >= 500
            ? 'errors/error5xx.html.twig'
            : 'errors/error4xx.html.twig';

        $content = $this->twig->render(
            $template,
            [
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'message' => $message,
                'path' => $request->getPathInfo(),
            ],
        );

        $event->setResponse(
            new Response($content, $statusCode),
        );
    }

    private function resolveStatusCode(\Throwable $throwable): int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        if ($throwable instanceof AccessDeniedException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($throwable instanceof AuthenticationException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function shouldRedirectToLogin(Request $request, \Throwable $throwable, int $statusCode): bool
    {
        if ($statusCode !== Response::HTTP_FORBIDDEN && $statusCode !== Response::HTTP_UNAUTHORIZED) {
            return false;
        }

        if (!$throwable instanceof AccessDeniedException && !$throwable instanceof AuthenticationException) {
            return false;
        }

        $path = strtolower($request->getPathInfo());

        if (!str_starts_with($path, '/admin')) {
            return false;
        }

        return !str_starts_with($path, '/admin/login');
    }

    private function isApiRequest(Request $request): bool
    {
        $path = strtolower($request->getPathInfo());

        if (str_starts_with($path, '/api')) {
            return true;
        }

        $requestFormat = strtolower((string) $request->getRequestFormat(''));

        if ($requestFormat === 'json') {
            return true;
        }

        $accept = strtolower((string) $request->headers->get('Accept', ''));

        if (str_contains($accept, 'application/json') || str_contains($accept, '+json')) {
            return true;
        }

        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

        return str_contains($contentType, 'application/json') || str_contains($contentType, '+json');
    }

    private function resolvePublicMessage(int $statusCode, string $rawMessage): string
    {
        if ($statusCode >= 500) {
            if ($this->kernel->isDebug()) {
                $message = trim($rawMessage);

                if ($message !== '') {
                    return $message;
                }
            }

            return 'Une erreur interne est survenue.';
        }

        if ($this->kernel->isDebug()) {
            $message = trim($rawMessage);

            if ($message !== '') {
                return $message;
            }
        }

        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'La requete est invalide.',
            Response::HTTP_UNAUTHORIZED => 'Authentification requise.',
            Response::HTTP_FORBIDDEN => 'Acces refuse.',
            Response::HTTP_NOT_FOUND => 'La ressource demandee est introuvable.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Methode HTTP non autorisee.',
            Response::HTTP_NOT_ACCEPTABLE => 'Le format demande est non supporte.',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Les donnees envoyees sont invalides.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Trop de requetes. Merci de reessayer plus tard.',
            default => $this->resolveStatusText($statusCode),
        };
    }

    private function resolveStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'Requete incorrecte',
            Response::HTTP_UNAUTHORIZED => 'Non autorise',
            Response::HTTP_FORBIDDEN => 'Acces interdit',
            Response::HTTP_NOT_FOUND => 'Non trouve',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Methode non autorisee',
            Response::HTTP_NOT_ACCEPTABLE => 'Format non acceptable',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Entite non traitable',
            Response::HTTP_TOO_MANY_REQUESTS => 'Trop de requetes',
            Response::HTTP_INTERNAL_SERVER_ERROR => 'Erreur interne du serveur',
            default => 'Erreur HTTP',
        };
    }
}
