<?php declare(strict_types=1);

namespace App\DTO;
use App\Enum\ActivityType;

final class ActivityDto
{
    public function __construct(
        public int $id,
        public ?ActivityType $type,
        public string $details,
        public string $date,
        public array $lastComment
    ) {}
}