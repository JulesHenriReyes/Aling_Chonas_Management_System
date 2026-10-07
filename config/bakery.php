<?php

return [
    // Pickup date/time fields represent the bakery's local wall-clock time.
    // Existing payment and lifecycle timestamps remain stored in app timezone.
    'pickup_timezone' => env('BAKERY_PICKUP_TIMEZONE', 'Asia/Manila'),
    'pickup_opens_at' => env('BAKERY_PICKUP_OPENS_AT', '08:00'),
    'pickup_closes_at' => env('BAKERY_PICKUP_CLOSES_AT', '18:00'),
    'business_timezone' => env('BAKERY_BUSINESS_TIMEZONE', env('BAKERY_PICKUP_TIMEZONE', 'Asia/Manila')),
    'contact_phone' => env('BAKERY_CONTACT_PHONE', '0917 123 4567'),
    'facebook_name' => env('BAKERY_FACEBOOK_NAME', 'Aling Chona Cake & Cupcake'),
    'facebook_url' => env('BAKERY_FACEBOOK_URL', 'https://www.facebook.com/chonaejay.hinay#'),
    'location' => env('BAKERY_LOCATION', 'Aling Chona Store & Residence'),
];
