<?php declare(strict_types=1);

namespace App\DTO;
use App\Enum\ActivityType;
use App\DTO\CommentDto;

final class ActivityDto
{
    public function __construct(
        public int $id,
        public ?ActivityType $type,
        public string $details,
        public string $date,
        public ?CommentDto $lastComment,
        public int $commentsCount
    ) {}
}