<?php declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

final class OperatorRepository
{
    public function __construct(
        private readonly Explorer $db,
    ) {}

    public function findByEmail(string $email): ?ActiveRow
    {
        return $this->db->table('operators')
            ->where('email', $email)
            ->where('is_active', 1)
            ->fetch() ?: null;
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->db->table('operators')
            ->where('id', $id)
            ->where('is_active', 1)
            ->fetch() ?: null;
    }
}
