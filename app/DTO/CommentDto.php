<?php declare(strict_types=1);

namespace App\DTO;
use App\Enum\ActivityType;

final class CommentDto
{
    public function __construct(
        public int $id,
        public string $author,
        public string $body,
        public string $date
    ) {}
}