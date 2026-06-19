<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\CommentDto;
use Nette\Database\Explorer;

final class CommentRepository
{
    public function __construct(
        private readonly Explorer $db,
    ) {}

    /**
     * Loads comments for an activity together with the operator's name
     * via Nette Database's related-row syntax (ref()).
     */
    public function findByActivity(int $activityId): array
    {
        $selection = $this->db->table('comments')
            ->where('activity_id', $activityId)
            ->order('created_at ASC');

        $items = [];
        foreach ($selection as $row) {
            $operator = $row->ref('operators', 'operator_id');
            $items[] = new CommentDto(
                id: $row->id,
                operatorId: $row->operator_id,
                author: $operator?->name ?? 'Unknown operator',
                body: $row->body,
                date: $row->created_at->format('Y-m-d H:i:s'),
            );
        }

        return ['items' => $items];
    }

    /**
     * Inserts a new comment authored by the given operator and
     * returns it as a fully-resolved DTO (with operator name attached).
     */
    public function add(int $activityId, int $operatorId, string $body): CommentDto
    {
        $row = $this->db->table('comments')->insert([
            'activity_id' => $activityId,
            'operator_id' => $operatorId,
            'body'        => $body,
            'created_at'  => new \DateTimeImmutable(),
        ]);

        $operator = $row->ref('operators', 'operator_id');

        return new CommentDto(
            id: $row->id,
            operatorId: $row->operator_id,
            author: $operator?->name ?? 'Unknown operator',
            body: $row->body,
            date: $row->created_at->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @param int[] $activityIds
     * @return array<int, CommentDto> Масив виду [activityId => CommentDto]
     */
    public function findLastCommentsForActivities(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        $rows = $this->db->query(
            '
            SELECT c.*, o.name AS operator_name
            FROM comments c
            INNER JOIN (
                SELECT activity_id, MAX(id) AS last_comment_id
                FROM comments
                WHERE activity_id IN (?)
                GROUP BY activity_id
            ) lc
                ON c.id = lc.last_comment_id
            LEFT JOIN operators o ON o.id = c.operator_id
            ',
            $activityIds
        )->fetchAll();


        $result = [];

        foreach ($rows as $row) {
            $result[$row->activity_id] = new CommentDto(
                id: $row->id,
                operatorId: $row->operator_id,
                author: $row->operator_name,
                body: $row->body,
                date: $row->created_at->format('Y-m-d H:i:s'),
            );
        }

        return $result;
    }

    /**
     * @param int[] $activityIds
     * @return array<int, int> [activityId => count]
     */
    public function countCommentsForActivities(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        $rows = $this->db->table('comments')
            ->select('activity_id, COUNT(*) AS count')
            ->where('activity_id', $activityIds)
            ->group('activity_id')
            ->fetchAll();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->activity_id] = (int)$row->count;
        }

        return $result;
    }
}
