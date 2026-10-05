<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class PickupCalendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('bakery.pickup_timezone'))->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }
}
