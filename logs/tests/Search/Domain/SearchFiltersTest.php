<?php

declare(strict_types=1);

namespace App\Tests\Search\Domain;

use App\Search\Domain\SearchFilters;
use PHPUnit\Framework\TestCase;

/**
 * Tests des filtres combinables Search.
 */
final class SearchFiltersTest extends TestCase
{
    /**
     * But : Vérifier que les filtres sont normalisés et combinables.
     *
     * Entrée : level/domain/fingerprint avec espaces et casse mixte.
     * Résultat attendu : valeurs trim + lowercase.
     */
    public function testNormalizesStringFilters(): void
    {
        $filters = new SearchFilters(
            fromDate: new \DateTimeImmutable('2026-05-01 00:00:00'),
            toDate: new \DateTimeImmutable('2026-05-31 23:59:59'),
            level: ' ERROR ',
            domain: ' Billing ',
            projectId: 7,
            fingerprint: ' ABCDEF1234567890 ',
        );

        self::assertSame('error', $filters->getLevel());
        self::assertSame('billing', $filters->getDomain());
        self::assertSame('abcdef1234567890', $filters->getFingerprint());
        self::assertSame(7, $filters->getProjectId());
    }

    /**
     * But : Vérifier qu'une plage de dates invalide est refusée.
     *
     * Entrée : fromDate > toDate.
     * Résultat attendu : InvalidArgumentException.
     */
    public function testRejectsInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchFilters(
            fromDate: new \DateTimeImmutable('2026-06-01 00:00:00'),
            toDate: new \DateTimeImmutable('2026-05-01 00:00:00'),
        );
    }
}
