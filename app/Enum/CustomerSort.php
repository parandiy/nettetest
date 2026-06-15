<?php declare(strict_types=1);

namespace App\Enum;

enum CustomerSort: string
{
    case NAME = 'name';
    case CREATED_AT = 'created_at';
    case EMAIL = 'email';
    case IS_ACTIVE = 'is_active';
}