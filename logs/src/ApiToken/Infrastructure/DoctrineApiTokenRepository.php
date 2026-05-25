<?php

declare(strict_types=1);

namespace App\ApiToken\Infrastructure;

use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenToStore;
use Doctrine\DBAL\Connection;

/**
 * Repository DBAL pour tokens API hashés.
 */
final readonly class DoctrineApiTokenRepository implements ApiTokenRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function store(ApiTokenToStore $tokenToStore): void
    {
        try {
            $this->connection->insert(
                'api_tokens',
                [
                    'project_id' => $tokenToStore->getProjectId(),
                    'token_hash' => $tokenToStore->getTokenHash(),
                    'token_prefix' => $tokenToStore->getTokenPrefix(),
                    'label' => $tokenToStore->getLabel(),
                    'expires_at' => $tokenToStore
                        ->getExpiresAt()
                        ?->format('Y-m-d H:i:s'),
                ],
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token hash persistence failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
