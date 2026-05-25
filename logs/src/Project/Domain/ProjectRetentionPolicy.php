<?php

declare(strict_types=1);

namespace App\Project\Domain;

/**
 * Politique de rétention basée
 * sur retention_days du projet.
 */
final class ProjectRetentionPolicy
{
    /**
     * Calcule la date limite de conservation.
     */
    public function buildCutoffDate(
        Project $project,
        \DateTimeImmutable $now,
    ): \DateTimeImmutable {
        return $now->modify(
            sprintf(
                '-%d days',
                $project->getRetentionDays(),
            ),
        );
    }

    /**
     * Indique si un log daté doit être purgé.
     */
    public function shouldPurgeLogDate(
        Project $project,
        \DateTimeImmutable $logCreatedAt,
        \DateTimeImmutable $now,
    ): bool {
        $cutoff = $this->buildCutoffDate(
            $project,
            $now,
        );

        return $logCreatedAt < $cutoff;
    }
}
