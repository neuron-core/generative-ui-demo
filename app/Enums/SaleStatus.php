<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
