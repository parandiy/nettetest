<?php declare(strict_types=1);

namespace App\DTO;
final class CustomerDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $notes,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}