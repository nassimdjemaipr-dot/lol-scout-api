<?php

declare(strict_types=1);

namespace App\Enum;

enum PlayerRole: string
{
    case TOP = 'TOP';
    case JUNGLE = 'JUNGLE';
    case MID = 'MID';
    case ADC = 'ADC';
    case SUPPORT = 'SUPPORT';
}
