<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\CommentDto;
use Nette\Database\Explorer;

final class CommentRepository
{
    public function __construct(
        private readonly Explorer $db
    ) {}
    public function findByActivity(int $activityId): array
    {
        $selection = $this->db->table('comments')->where('activity_id', $activityId);

        $items = [];
        foreach ($selection->fetchAll() as $row) {
            $items[] = new CommentDto(
                id: $row->id,
                author: $row->author_name,
                body: $row->body,
                date: $row->created_at->format('Y-m-d H:i:s'),
            );
        }
        return [
            'items' => $items,
        ];
    }
}