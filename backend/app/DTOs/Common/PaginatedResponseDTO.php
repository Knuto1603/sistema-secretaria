<?php

namespace App\DTOs\Common;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class PaginatedResponseDTO
{
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total
    ) {}

    public static function fromPaginator(LengthAwarePaginator $paginator, array $items): self
    {
        return new self(
            items: $items,
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total()
        );
    }

    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'pagination' => [
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
            ]
        ];
    }
}
