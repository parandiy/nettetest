<?php declare(strict_types=1);

namespace App\DTO;

final class PagedResult
{
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}