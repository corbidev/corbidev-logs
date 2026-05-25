<?php

declare(strict_types=1);

namespace App\Tests\Project\Domain;

use App\Project\Domain\Project;
use PHPUnit\Framework\TestCase;

/**
 * Tests du modèle domaine initial Project.
 */
final class ProjectTest extends TestCase
{
    /**
     * But : Vérifier qu'un Project valide conserve les champs attendus.
     *
     * Entrée : id=2, slug='billing-api', name='Billing API', retentionDays=45, isActive=true.
     * Résultat attendu : getters cohérents et immutables.
     */
    public function testProjectKeepsExpectedValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-05-25 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-05-25 11:00:00');

        $project = new Project(
            id: 2,
            slug: 'billing-api',
            name: 'Billing API',
            retentionDays: 45,
            isActive: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertSame(2, $project->getId());
        self::assertSame('billing-api', $project->getSlug());
        self::assertSame('Billing API', $project->getName());
        self::assertSame(45, $project->getRetentionDays());
        self::assertTrue($project->isActive());
        self::assertSame($createdAt, $project->getCreatedAt());
        self::assertSame($updatedAt, $project->getUpdatedAt());
    }

    /**
     * But : Vérifier que Project::default() expose un modèle initial cohérent.
     *
     * Entrée : aucune.
     * Résultat attendu : id=1, slug=default, name='Default project', retentionDays=30, actif.
     */
    public function testDefaultReturnsInitialProjectModel(): void
    {
        $project = Project::default();

        self::assertSame(1, $project->getId());
        self::assertSame('default', $project->getSlug());
        self::assertSame('Default project', $project->getName());
        self::assertSame(30, $project->getRetentionDays());
        self::assertTrue($project->isActive());
    }

    /**
     * But : Vérifier que le constructeur refuse les valeurs invalides minimales.
     *
     * Entrée : id <= 0, slug vide, name vide, retentionDays <= 0.
     * Résultat attendu : InvalidArgumentException levée.
     */
    public function testProjectRejectsInvalidMinimalValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Project(
            id: 0,
            slug: '',
            name: '',
            retentionDays: 0,
            isActive: true,
            createdAt: new \DateTimeImmutable('now'),
            updatedAt: new \DateTimeImmutable('now'),
        );
    }
}
