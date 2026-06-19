<?php
declare(strict_types=1);

namespace App\Repository;

use App\DTO\ActivityDto;
use App\DTO\Filter\ActivityFilter;
use App\DTO\PagedResult;
use App\Enum\ActivityType;
use Nette\Database\Explorer;

final class ActivityRepository
{
    public function __construct(
        private readonly Explorer $db,
        private readonly CommentRepository $commentRepository,
    ) {
    }

    public function findPaged(ActivityFilter $filter, int $limit, int $offset): PagedResult
    {
        $selection = $this->db->table('activities')
            ->where('customer_id', $filter->customerId);

        if ($filter->q) {
            $selection->where('details LIKE ?', "%{$filter->q}%");
        }

        if ($filter->activityType !== null) {
            $selection->where('activity_type', $filter->activityType);
        }

        $total = (clone $selection)->count('*');

        $selection
            ->order('created_at DESC')
            ->limit($limit, $offset);

        $activitiesData = $selection->fetchAll();

        $activityIds = array_map(
            fn($row) => $row->id,
            $activitiesData
        );

        $lastComments = $this->commentRepository
            ->findLastCommentsForActivities($activityIds);

        $commentsCount = $this->commentRepository
            ->countCommentsForActivities($activityIds);

        $items = [];
        foreach ($activitiesData as $row) {
            $items[] = new ActivityDto(
                id: $row->id,
                type: ActivityType::tryFrom($row->activity_type ?? '') ?? null,
                details: $row->details,
                date: $row->created_at->format('Y-m-d H:i:s'),
                lastComment: $lastComments[$row->id] ?? null,
                commentsCount: $commentsCount[$row->id] ?? 0,
            );
        }

        return new PagedResult(
            items: $items,
            total: $total,
            page: $filter->page,
            perPage: $limit
        );
    }
}
