<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Filter\CustomerFilter;
use App\DTO\PagedResult;
use App\DTO\CustomerDto;
use Nette\Database\Explorer;

final class CustomerRepository
{
    public function __construct(
        private readonly Explorer $db
    ) {}

    public function findPaged(CustomerFilter $filter, int $limit, int $offset): PagedResult
    {
        $selection = $this->db->table('customers');

        if ($filter->q) {
            $selection->where('name LIKE ?', "%{$filter->q}%");
        }

        if ($filter->isActive !== null) {
            $selection->where('is_active', $filter->isActive);
        }

        $total = (clone $selection)->count('*');

        $selection
            ->order($filter->sort->value . ' ' . $filter->dir->value)
            ->limit($limit, $offset);

        $items = [];
        foreach ($selection->fetchAll() as $row) {
            $items[] = new CustomerDto(
                id: $row->id,
                name: $row->name,
                email: $row->email,
                phone: $row->phone,
                notes: $row->notes,
                isActive: (bool) $row->is_active,
                createdAt: $row->created_at->format('Y-m-d H:i:s'),
                updatedAt: $row->updated_at->format('Y-m-d H:i:s'),
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
