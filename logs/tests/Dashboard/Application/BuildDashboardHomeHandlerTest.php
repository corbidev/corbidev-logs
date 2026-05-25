<?php

declare(strict_types=1);

namespace App\Tests\Dashboard\Application;

use App\Dashboard\Application\BuildDashboardHomeHandler;
use App\Dashboard\Domain\DashboardReadModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests du handler Dashboard.
 */
final class BuildDashboardHomeHandlerTest extends TestCase
{
    /**
     * But : Vérifier que le handler délègue uniquement au read model.
     *
     * Entrée : read model mock retournant des compteurs.
     * Résultat attendu : compteurs retournés inchangés.
     */
    public function testHandleReturnsReadModelCounters(): void
    {
        $readModel = $this->createMock(
            DashboardReadModelInterface::class,
        );

        $readModel
            ->expects(self::once())
            ->method('getGlobalCounters')
            ->willReturn([
                'total_logs' => 120,
                'total_projects' => 4,
                'failed_ingestions' => 2,
            ]);

        $handler = new BuildDashboardHomeHandler(
            $readModel,
        );

        $result = $handler->handle();

        self::assertSame(120, $result['total_logs']);
        self::assertSame(4, $result['total_projects']);
        self::assertSame(2, $result['failed_ingestions']);
    }
}
