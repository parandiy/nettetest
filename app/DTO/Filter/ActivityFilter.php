<?php declare(strict_types=1);

namespace App\DTO\Filter;

use App\Enum\ActivityType;

final class ActivityFilter
{
    public function __construct(
        public ?string $q,
        public ?ActivityType $activityType,
        public int $page,
        public int $customerId,
    ) {}
}