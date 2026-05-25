<?php

declare(strict_types=1);

namespace App\Tests\Project\Domain;

use App\Project\Domain\Project;
use App\Project\Domain\ProjectRetentionPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la politique de rétention Project.
 */
final class ProjectRetentionPolicyTest extends TestCase
{
    /**
     * But : Vérifier que la date de cutoff dépend bien de retention_days.
     *
     * Entrée : retentionDays=30, now=2026-05-25 00:00:00.
     * Résultat attendu : cutoff=2026-04-25 00:00:00.
     */
    public function testBuildCutoffDateUsesProjectRetentionDays(): void
    {
        $policy = new ProjectRetentionPolicy();

        $project = new Project(
            id: 3,
            slug: 'project-retention',
            name: 'Project Retention',
            retentionDays: 30,
            isActive: true,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $now = new \DateTimeImmutable('2026-05-25 00:00:00');

        $cutoff = $policy->buildCutoffDate(
            $project,
            $now,
        );

        self::assertSame(
            '2026-04-25 00:00:00',
            $cutoff->format('Y-m-d H:i:s'),
        );
    }

    /**
     * But : Vérifier que shouldPurgeLogDate() est true pour un log plus ancien que le cutoff.
     *
     * Entrée : logCreatedAt=2026-04-24, cutoff=2026-04-25.
     * Résultat attendu : true.
     */
    public function testShouldPurgeLogDateReturnsTrueWhenLogIsOlderThanCutoff(): void
    {
        $policy = new ProjectRetentionPolicy();

        $project = new Project(
            id: 4,
            slug: 'billing-retention',
            name: 'Billing Retention',
            retentionDays: 30,
            isActive: true,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $shouldPurge = $policy->shouldPurgeLogDate(
            $project,
            new \DateTimeImmutable('2026-04-24 23:59:59'),
            new \DateTimeImmutable('2026-05-25 00:00:00'),
        );

        self::assertTrue($shouldPurge);
    }

    /**
     * But : Vérifier que shouldPurgeLogDate() est false pour un log à la limite de rétention.
     *
     * Entrée : logCreatedAt=cutoff exact.
     * Résultat attendu : false.
     */
    public function testShouldPurgeLogDateReturnsFalseWhenLogIsAtCutoffBoundary(): void
    {
        $policy = new ProjectRetentionPolicy();

        $project = new Project(
            id: 5,
            slug: 'ops-retention',
            name: 'Ops Retention',
            retentionDays: 15,
            isActive: true,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $shouldPurge = $policy->shouldPurgeLogDate(
            $project,
            new \DateTimeImmutable('2026-05-10 00:00:00'),
            new \DateTimeImmutable('2026-05-25 00:00:00'),
        );

        self::assertFalse($shouldPurge);
    }
}
