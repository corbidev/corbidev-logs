<?php

declare(strict_types=1);

namespace App\Tests\Dashboard\Domain;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie que le squelette Dashboard
 * reste orienté read-side.
 */
final class DashboardReadSideSeparationTest extends TestCase
{
    /**
     * But : Vérifier que les classes Dashboard initiales n'importent pas les modules write-side.
     *
     * Entrée : sources Dashboard Application/Domain/Infrastructure.
     * Résultat attendu : aucune dépendance vers Ingestion/Queue/Persistence.
     */
    public function testDashboardInitialSkeletonDoesNotDependOnWriteSideModules(): void
    {
        $files = [
            __DIR__ . '/../../../src/Dashboard/Domain/DashboardReadModelInterface.php',
            __DIR__ . '/../../../src/Dashboard/Application/BuildDashboardHomeHandler.php',
            __DIR__ . '/../../../src/Dashboard/Infrastructure/InMemoryDashboardReadModel.php',
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            self::assertIsString($content);
            self::assertStringNotContainsString('App\\Ingestion\\', $content);
            self::assertStringNotContainsString('App\\Queue\\', $content);
            self::assertStringNotContainsString('App\\Persistence\\', $content);
        }
    }
}
