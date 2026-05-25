<?php

declare(strict_types=1);

namespace App\Search\Domain;

/**
 * Résultat paginé d'une requête Search.
 */
final readonly class SearchResult
{
    /**
     * @param list<SearchLogEntryView> $items
     */
    public function __construct(
        private array $items,
        private int $page,
        private int $perPage,
        private int $totalCount,
    ) {
    }

    /**
     * @return list<SearchLogEntryView>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }
}
