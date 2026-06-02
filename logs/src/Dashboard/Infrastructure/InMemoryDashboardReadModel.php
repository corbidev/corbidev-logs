<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardReadModelInterface;

/**
 * Implémentation minimale read-side
 * pour initialiser le module Dashboard.
 */
final class InMemoryDashboardReadModel implements DashboardReadModelInterface
{
    /**
     * {@inheritDoc}
     */
    public function getGlobalCounters(): array
    {
        return [
            'total_logs' => 0,
            'total_domains' => 0,
            'failed_ingestions' => 0,
        ];
    }
}
