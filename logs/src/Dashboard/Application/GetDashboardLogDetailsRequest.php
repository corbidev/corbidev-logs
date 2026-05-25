<?php

declare(strict_types=1);

namespace App\Dashboard\Application;

/**
 * Requête applicative pour le détail d'un log Dashboard.
 */
final readonly class GetDashboardLogDetailsRequest
{
    public function __construct(
        private string $externalId,
    ) {
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }
}