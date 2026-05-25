<?php

declare(strict_types=1);

namespace App\Tests\Search\Domain;

use App\Search\Domain\SearchPagination;
use PHPUnit\Framework\TestCase;

/**
 * Tests de pagination Search bornée.
 */
final class SearchPaginationTest extends TestCase
{
    /**
     * But : Vérifier le calcul d'offset avec une pagination valide.
     *
     * Entrée : page=3, perPage=20.
     * Résultat attendu : offset=40.
     */
    public function testGetOffsetReturnsExpectedValue(): void
    {
        $pagination = new SearchPagination(
            page: 3,
            perPage: 20,
        );

        self::assertSame(40, $pagination->getOffset());
    }

    /**
     * But : Vérifier que page <= 0 est refusé.
     *
     * Entrée : page=0.
     * Résultat attendu : InvalidArgumentException.
     */
    public function testRejectsInvalidPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchPagination(
            page: 0,
            perPage: 20,
        );
    }

    /**
     * But : Vérifier que perPage trop grand est refusé.
     *
     * Entrée : perPage=201.
     * Résultat attendu : InvalidArgumentException.
     */
    public function testRejectsPerPageOverConfiguredLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchPagination(
            page: 1,
            perPage: 201,
        );
    }
}
