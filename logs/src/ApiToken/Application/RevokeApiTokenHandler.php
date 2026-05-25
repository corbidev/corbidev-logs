<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;

/**
 * Orchestrateur de révocation
 * d'un token API.
 */
final readonly class RevokeApiTokenHandler
{
    public function __construct(
        private ApiTokenHasherInterface $hasher,
        private ApiTokenRepositoryInterface $repository,
    ) {
    }

    /**
     * Révoque le token ciblé.
     *
     * Retourne true si un token actif
     * a été révoqué.
     */
    public function handle(
        RevokeApiTokenRequest $request,
    ): bool {
        try {
            $tokenHash = $this->hasher->hash(
                $request->getPlainToken(),
            );

            return $this->repository->revokeByHash(
                $tokenHash,
                $request->getRevokedAt(),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token revocation failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
