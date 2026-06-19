<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Filter\ActivityFilter;
use App\DTO\PagedResult;
use App\Repository\ActivityRepository;

final class ActivityService
{
    const ITEMS_PER_PAGE = 10;

    public function __construct(
        private readonly ActivityRepository $activityRepository
    ) {}

    public function getActivities(ActivityFilter $filter): PagedResult
    {
        $offset = ($filter->page - 1) * self::ITEMS_PER_PAGE;

        return $this->activityRepository->findPaged($filter, self::ITEMS_PER_PAGE, $offset);
    }
}
