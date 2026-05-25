<?php

declare(strict_types=1);

namespace App\Dashboard\Domain;

/**
 * Contrat de lecture d'un log détaillé pour le Dashboard.
 */
interface DashboardLogDetailsRepositoryInterface
{
    /**
     * Retourne un log détaillé par externalId ou null si introuvable.
     */
    public function findByExternalId(
        string $externalId,
    ): ?DashboardLogDetailsView;
}