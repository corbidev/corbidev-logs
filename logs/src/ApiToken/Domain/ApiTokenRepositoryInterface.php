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
}
