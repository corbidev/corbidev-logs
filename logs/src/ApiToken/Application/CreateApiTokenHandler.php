<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

use App\ApiToken\Domain\ApiTokenGeneratorInterface;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenToStore;

/**
 * Orchestrateur de création
 * d'un token API opaque.
 */
final readonly class CreateApiTokenHandler
{
    private const int TOKEN_PREFIX_LENGTH = 12;

    public function __construct(
        private ApiTokenGeneratorInterface $generator,
        private ApiTokenHasherInterface $hasher,
        private ApiTokenRepositoryInterface $repository,
    ) {
    }

    public function handle(
        CreateApiTokenRequest $request,
    ): CreateApiTokenResult {
        try {
            $plainToken = $this->generator->generate();

            if (trim($plainToken) === '') {
                throw new \RuntimeException(
                    'Generated token is empty.',
                );
            }

            $tokenHash = $this->hasher->hash(
                $plainToken,
            );

            $tokenPrefix = mb_substr(
                $plainToken,
                0,
                self::TOKEN_PREFIX_LENGTH,
            );

            $this->repository->store(
                new ApiTokenToStore(
                    projectId: $request->getProjectId(),
                    tokenHash: $tokenHash,
                    tokenPrefix: $tokenPrefix,
                    label: $request->getLabel(),
                    expiresAt: $request->getExpiresAt(),
                ),
            );

            return new CreateApiTokenResult(
                plainToken: $plainToken,
                tokenPrefix: $tokenPrefix,
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token creation failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
