<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Monitor = 'monitor';
    case Phone = 'phone';
    case Tablet = 'tablet';
    case Printer = 'printer';
    case NetworkEquipment = 'network_equipment';
    case Other = 'other';
}
