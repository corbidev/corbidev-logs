<?php

declare(strict_types=1);

namespace App\ApiToken\Domain;

/**
 * Contrat de hash d'un token opaque.
 */
interface ApiTokenHasherInterface
{
    /**
     * Hash un token en représentation stable.
     */
    public function hash(string $plainToken): string;
}
