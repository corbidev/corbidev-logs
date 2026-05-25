<?php

declare(strict_types=1);

namespace App\Search\Domain;

/**
 * Pagination bornée obligatoire pour Search.
 */
final readonly class SearchPagination
{
    private const int MAX_PER_PAGE = 200;

    public function __construct(
        private int $page,
        private int $perPage,
    ) {
        if ($this->page <= 0) {
            throw new \InvalidArgumentException(
                'Search page must be greater than zero.',
            );
        }

        if ($this->perPage <= 0) {
            throw new \InvalidArgumentException(
                'Search per page must be greater than zero.',
            );
        }

        if ($this->perPage > self::MAX_PER_PAGE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Search per page must be lower or equal to %d.',
                    self::MAX_PER_PAGE,
                ),
            );
        }
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
