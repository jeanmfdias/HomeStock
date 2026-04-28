<?php

declare(strict_types=1);

namespace App\Entity;

enum UnitType: string
{
    case UNIT = 'unit';
    case G = 'g';
    case KG = 'kg';
    case ML = 'ml';
    case L = 'l';
}
