<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class InventoryCalendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('bakery.business_timezone', 'Asia/Manila'))->startOfDay();
    }

    public static function date(): string { return self::today()->toDateString(); }
}
