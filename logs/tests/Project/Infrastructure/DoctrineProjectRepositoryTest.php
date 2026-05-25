<?php

declare(strict_types=1);

namespace App\Tests\Project\Infrastructure;

use App\Project\Infrastructure\DoctrineProjectRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests du repository Doctrine Project.
 */
final class DoctrineProjectRepositoryTest extends TestCase
{
    /**
     * But : Vérifier que findById() mappe retention_days depuis SQL.
     *
     * Entrée : row SQL avec retention_days=45.
     * Résultat attendu : Project retourné avec retentionDays=45.
     */
    public function testFindByIdMapsRetentionDaysFromSqlRow(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 9,
                'slug' => 'billing',
                'name' => 'Billing',
                'retention_days' => 45,
                'is_active' => 1,
                'created_at' => '2026-05-01 00:00:00',
                'updated_at' => '2026-05-20 00:00:00',
            ]);

        $repository = new DoctrineProjectRepository(
            $connection,
        );

        $project = $repository->findById(9);

        self::assertNotNull($project);
        self::assertSame(9, $project->getId());
        self::assertSame(45, $project->getRetentionDays());
        self::assertSame('billing', $project->getSlug());
    }

    /**
     * But : Vérifier que findBySlug() retourne null si aucun projet n'est trouvé.
     *
     * Entrée : fetchAssociative()=false.
     * Résultat attendu : null.
     */
    public function testFindBySlugReturnsNullWhenProjectDoesNotExist(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $repository = new DoctrineProjectRepository(
            $connection,
        );

        $project = $repository->findBySlug('missing-project');

        self::assertNull($project);
    }

    /**
     * But : Vérifier que findById() remonte une erreur technique explicite en cas de panne SQL.
     *
     * Entrée : fetchAssociative() lève RuntimeException('db down').
     * Résultat attendu : RuntimeException préfixée 'Project query failed:'.
     */
    public function testFindByIdThrowsExplicitTechnicalErrorOnSqlFailure(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willThrowException(
                new \RuntimeException('db down'),
            );

        $repository = new DoctrineProjectRepository(
            $connection,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project query failed: db down');

        $repository->findById(1);
    }
}
