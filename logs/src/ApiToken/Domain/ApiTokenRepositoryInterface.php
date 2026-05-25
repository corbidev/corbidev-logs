<?php

declare(strict_types=1);

namespace App\ApiToken\Domain;

/**
 * Contrat de persistence des tokens API hashés.
 */
interface ApiTokenRepositoryInterface
{
    /**
     * Persiste un token hashé.
     */
    public function store(\App\ApiToken\Domain\ApiTokenToStore $tokenToStore): void;

    /**
     * Résout l'état courant d'un token hashé.
     */
    public function resolveStateByHash(
        string $tokenHash,
        \DateTimeImmutable $now,
    ): ApiTokenState;

    /**
     * Révoque un token hashé.
     */
    public function revokeByHash(
        string $tokenHash,
        \DateTimeImmutable $revokedAt,
    ): bool;
}
