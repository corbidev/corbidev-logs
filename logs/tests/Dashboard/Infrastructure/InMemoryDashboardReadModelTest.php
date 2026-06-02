<?php

declare(strict_types=1);

namespace App\Tests\Dashboard\Infrastructure;

use App\Dashboard\Infrastructure\InMemoryDashboardReadModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests du read model Dashboard minimal.
 */
final class InMemoryDashboardReadModelTest extends TestCase
{
    /**
     * But : Vérifier que le read model retourne des compteurs stables.
     *
     * Entrée : aucune.
     * Résultat attendu : clés attendues présentes avec valeurs entières.
     */
    public function testGetGlobalCountersReturnsStableShape(): void
    {
        $model = new InMemoryDashboardReadModel();

        $counters = $model->getGlobalCounters();

        self::assertArrayHasKey('total_logs', $counters);
        self::assertArrayHasKey('total_domains', $counters);
        self::assertArrayHasKey('failed_ingestions', $counters);

        self::assertSame(0, $counters['total_logs']);
        self::assertSame(0, $counters['total_domains']);
        self::assertSame(0, $counters['failed_ingestions']);
    }
}
