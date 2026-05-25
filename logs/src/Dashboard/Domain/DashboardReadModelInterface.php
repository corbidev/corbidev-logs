<?php

declare(strict_types=1);

namespace App\Dashboard\Domain;

/**
 * Contrat read-side du Dashboard.
 *
 * IMPORTANT :
 * Ce contrat est limité à la lecture
 * et ne doit pas piloter le write side.
 */
interface DashboardReadModelInterface
{
    /**
     * Retourne les compteurs globaux affichables.
     *
     * @return array{
     *     total_logs:int,
     *     total_projects:int,
     *     failed_ingestions:int
     * }
     */
    public function getGlobalCounters(): array;
}
