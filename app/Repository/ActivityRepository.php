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
        private readonly Explorer $db
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

        $lastComment = [
            'id' => 10101,
            'author' => 'Support Agent',
            'date' => '2024-11-06 12:00',
            'body' => 'Payment confirmed, plan activated.'
        ]; // TEMP TEMP

        $items = [];
        foreach ($selection->fetchAll() as $row) {
            $items[] = new ActivityDto(
                id: $row->id,
                type: ActivityType::tryFrom($row->activity_type ?? '') ?? null,
                details: $row->details,
                date: $row->created_at->format('Y-m-d H:i:s'),
                lastComment: $lastComment,
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
