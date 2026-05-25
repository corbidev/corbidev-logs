<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenState;

/**
 * Orchestrateur de validation
 * d'un token API.
 */
final readonly class ValidateApiTokenHandler
{
    public function __construct(
        private ApiTokenHasherInterface $hasher,
        private ApiTokenRepositoryInterface $repository,
    ) {
    }

    public function handle(
        ValidateApiTokenRequest $request,
    ): ValidateApiTokenResult {
        try {
            $tokenHash = $this->hasher->hash(
                $request->getPlainToken(),
            );

            $state = $this->repository->resolveStateByHash(
                $tokenHash,
                $request->getNow(),
            );

            return match ($state) {
                ApiTokenState::ACTIVE => ValidateApiTokenResult::accepted(),
                ApiTokenState::REVOKED => ValidateApiTokenResult::refused('token_revoked'),
                ApiTokenState::EXPIRED => ValidateApiTokenResult::refused('token_expired'),
                ApiTokenState::NOT_FOUND => ValidateApiTokenResult::refused('token_not_found'),
            };
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token validation failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
