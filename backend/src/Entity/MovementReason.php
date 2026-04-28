<?php

declare(strict_types=1);

namespace App\Entity;

enum MovementReason: string
{
    case PURCHASE = 'purchase';
    case CONSUME = 'consume';
    case DISCARD = 'discard';
    case ADJUST = 'adjust';
}
