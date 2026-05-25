<?php

declare(strict_types=1);

namespace App\Dashboard\Application;

use App\Dashboard\Domain\DashboardReadModelInterface;

/**
 * Point d'entrée applicatif
 * du Dashboard read-side.
 */
final readonly class BuildDashboardHomeHandler
{
    public function __construct(
        private DashboardReadModelInterface $readModel,
    ) {
    }

    /**
     * @return array{
     *     total_logs:int,
     *     total_projects:int,
     *     failed_ingestions:int
     * }
     */
    public function handle(): array
    {
        return $this->readModel->getGlobalCounters();
    }
}
