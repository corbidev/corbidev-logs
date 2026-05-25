<?php

declare(strict_types=1);

namespace App\ApiToken\Domain;

/**
 * Contrat de génération d'un token opaque.
 */
interface ApiTokenGeneratorInterface
{
    /**
     * Génère un token opaque utilisable côté client.
     */
    public function generate(): string;
}
