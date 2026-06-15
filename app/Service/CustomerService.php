<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Filter\CustomerFilter;
use App\DTO\PagedResult;
use App\Repository\CustomerRepository;

final class CustomerService
{
    const ITEMS_PER_PAGE = 10;

    public function __construct(
        private readonly CustomerRepository $customerRepository
    ) {}

    public function getCustomers(CustomerFilter $filter): PagedResult
    {
        $offset = ($filter->page - 1) * self::ITEMS_PER_PAGE;

        return $this->customerRepository->findPaged($filter, self::ITEMS_PER_PAGE, $offset);
    }
}