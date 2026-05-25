<?php

namespace App\Jwt\Http;

use App\Jwt\Handler\ValidateHmacHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class HmacRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ValidateHmacHandler $validator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // ⚠️ uniquement requêtes principales
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // ❌ On ne protège QUE /api/jwt
        if (!$this->isProtectedPath($path)) {
            return;
        }

        // ❌ Routes publiques (ex: création client)
        if ($this->isPublicRoute($path)) {
            return;
        }

        // ❌ Si endpoint protégé MAIS pas de signature → rejet
        if (!$request->headers->has('X-SIGNATURE')) {
            throw new AccessDeniedHttpException('Missing HMAC signature');
        }

        // 🔐 Validation HMAC
        $this->validator->handle($request);
    }

    /**
     * Routes protégées par HMAC
     */
    private function isProtectedPath(string $path): bool
    {
        return str_starts_with($path, '/api/jwt');
    }

    /**
     * Routes publiques (ex: onboarding client)
     */
    private function isPublicRoute(string $path): bool
    {
        // création client + rotation autorisées sans HMAC (à ajuster si besoin)
        return str_starts_with($path, '/api/jwt/clients');
    }
}
