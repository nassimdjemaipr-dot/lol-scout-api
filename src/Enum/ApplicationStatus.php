<?php

declare(strict_types=1);

namespace App\Enum;

enum ApplicationStatus: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case ACCEPTEE = 'ACCEPTEE';
    case REFUSEE = 'REFUSEE';
}
