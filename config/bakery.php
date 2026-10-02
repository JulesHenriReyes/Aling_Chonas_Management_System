<?php

return [
    // Pickup date/time fields represent the bakery's local wall-clock time.
    // Existing payment and lifecycle timestamps remain stored in app timezone.
    'pickup_timezone' => env('BAKERY_PICKUP_TIMEZONE', 'Asia/Manila'),
    'business_timezone' => env('BAKERY_BUSINESS_TIMEZONE', env('BAKERY_PICKUP_TIMEZONE', 'Asia/Manila')),
];
