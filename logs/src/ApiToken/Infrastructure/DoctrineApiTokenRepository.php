<?php

declare(strict_types=1);

namespace App\ApiToken\Infrastructure;

use App\ApiToken\Domain\ApiTokenState;
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
                    // Keep project_id mirrored during transition until full cutover.
                    'project_id' => $tokenToStore->getDomainId(),
                    'domain_id' => $tokenToStore->getDomainId(),
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

    public function resolveStateByHash(
        string $tokenHash,
        \DateTimeImmutable $now,
    ): ApiTokenState {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT revoked_at, expires_at FROM api_tokens WHERE token_hash = :token_hash LIMIT 1',
                [
                    'token_hash' => $tokenHash,
                ],
            );

            if (!is_array($row)) {
                return ApiTokenState::NOT_FOUND;
            }

            if (($row['revoked_at'] ?? null) !== null) {
                return ApiTokenState::REVOKED;
            }

            $expiresAtRaw = $row['expires_at'] ?? null;

            if ($expiresAtRaw === null) {
                return ApiTokenState::ACTIVE;
            }

            if (!is_string($expiresAtRaw) || trim($expiresAtRaw) === '') {
                return ApiTokenState::ACTIVE;
            }

            try {
                $expiresAt = new \DateTimeImmutable($expiresAtRaw);
            } catch (\Throwable) {
                return ApiTokenState::EXPIRED;
            }

            if ($expiresAt <= $now) {
                return ApiTokenState::EXPIRED;
            }

            return ApiTokenState::ACTIVE;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token state resolution failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    public function revokeByHash(
        string $tokenHash,
        \DateTimeImmutable $revokedAt,
    ): bool {
        try {
            $affectedRows = $this->connection->executeStatement(
                'UPDATE api_tokens SET revoked_at = :revoked_at WHERE token_hash = :token_hash AND revoked_at IS NULL',
                [
                    'revoked_at' => $revokedAt->format('Y-m-d H:i:s'),
                    'token_hash' => $tokenHash,
                ],
            );

            return $affectedRows > 0;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Api token revocation persistence failed: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }
}
