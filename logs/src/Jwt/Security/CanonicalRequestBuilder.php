<?php

namespace App\Jwt\Security;

use Symfony\Component\HttpFoundation\Request;

final class CanonicalRequestBuilder
{
    public function build(Request $request): string
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getPathInfo());
        $this->assertValidPath($path);

        $bodyHash = $this->hashBody($request);
        $timestamp = $this->getHeader($request, 'X-TIMESTAMP');
        $nonce = $this->getHeader($request, 'X-NONCE');

        return implode("\n", [
            $method,
            $path,
            $bodyHash,
            $timestamp,
            $nonce,
        ]);
    }

    /**
     * Normalise le path pour éviter toute divergence
     */
    private function normalizePath(string $path): string
    {
        // supprime trailing slash sauf racine
        $normalized = rtrim($path, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * 🔐 Force les routes HMAC à rester dans /api/jwt
     */
    private function assertValidPath(string $path): void
    {
        if (!str_starts_with($path, '/api/jwt')) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid HMAC path "%s". Must start with "/api/jwt".',
                $path
            ));
        }
    }

    /**
     * Hash strict du body brut
     */
    private function hashBody(Request $request): string
    {
        $content = $request->getContent();

        // IMPORTANT : toujours hash même vide
        return hash('sha256', $content ?? '');
    }

    /**
     * Récupère un header obligatoire
     */
    private function getHeader(Request $request, string $name): string
    {
        $value = $request->headers->get($name);

        if ($value === null || $value === '') {
            throw new \InvalidArgumentException(sprintf(
                'Missing required header "%s" for HMAC signature.',
                $name
            ));
        }

        return $value;
    }
}
