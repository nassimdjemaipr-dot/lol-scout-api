<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case PLAYER = 'ROLE_PLAYER';
    case CLUB = 'ROLE_CLUB';
    case ADMIN = 'ROLE_ADMIN';
}
