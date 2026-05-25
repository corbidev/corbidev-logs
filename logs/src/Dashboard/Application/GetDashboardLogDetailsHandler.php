<?php

declare(strict_types=1);

namespace App\Dashboard\Application;

use App\Dashboard\Domain\DashboardLogDetailsRepositoryInterface;
use App\Dashboard\Domain\DashboardLogDetailsView;

/**
 * Point d'entrée applicatif pour afficher le détail d'un log.
 */
final readonly class GetDashboardLogDetailsHandler
{
    public function __construct(
        private DashboardLogDetailsRepositoryInterface $repository,
    ) {
    }

    public function handle(
        GetDashboardLogDetailsRequest $request,
    ): ?DashboardLogDetailsView {
        return $this->repository->findByExternalId(
            $request->getExternalId(),
        );
    }
}