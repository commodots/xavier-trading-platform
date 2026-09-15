<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NEW = 'new';
    case PARTIAL = 'partial';
    case FILLED = 'filled';
    case CANCELED = 'canceled';
    case REJECTED = 'rejected';
}
