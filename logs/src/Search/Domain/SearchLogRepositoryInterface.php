<?php

declare(strict_types=1);

namespace App\Search\Domain;

/**
 * Contrat de lecture bornée des logs.
 */
interface SearchLogRepositoryInterface
{
    /**
     * Exécute une recherche paginée.
     */
    public function search(
        SearchPagination $pagination,
        SearchFilters $filters,
    ): SearchResult;
}
