<?php declare(strict_types=1);

namespace App\Enum;

enum ActivityType: string
{
    case LOGIN = 'login';
    case PURCHASE = 'purchase';
    case SUPPORT_TICKET = 'support_ticket';
    case PASSWORD_RESET = 'password_reset';
    case PROFILE_UPDATE = 'profile_update';
    case SUBSCRIPTION = 'subscription';
    case REFUND = 'refund';
    case NOTE = 'note';
}
