<?php declare(strict_types=1);

namespace App\DTO\Filter;

use App\Enum\CustomerSort;
use App\Enum\SortDirection;

final class CustomerFilter
{
    public function __construct(
            public ?string $q,
            public ?bool $isActive,
            public int $page,
            public CustomerSort $sort,
            public SortDirection $dir,
    ) {}
}