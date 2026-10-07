<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class PickupHours
{
    public static function bounds(): array
    {
        $opening = config('bakery.pickup_opens_at', '08:00');
        $closing = config('bakery.pickup_closes_at', '18:00');
        if (! self::isTime($opening) || ! self::isTime($closing) || $opening > $closing) {
            throw new \LogicException('Pickup hours must be an ordered same-day HH:mm range.');
        }

        return [$opening, $closing];
    }

    private static function isTime(mixed $time): bool
    {
        return is_string($time) && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time) === 1;
    }

    public static function contains(mixed $time): bool
    {
        [$opening, $closing] = self::bounds();

        return self::isTime($time) && $time >= $opening && $time <= $closing;
    }

    public static function label(string $time): string
    {
        [$hour, $minute] = explode(':', $time);

        return ((int) $hour % 12 ?: 12).':'.$minute.((int) $hour < 12 ? ' AM' : ' PM');
    }

    public static function message(): string
    {
        [$opening, $closing] = self::bounds();

        return 'Choose a pickup time between '.self::label($opening).' and '.self::label($closing).'.';
    }

    public static function assertAllowed(mixed $time): void
    {
        if (! self::contains($time)) {
            throw ValidationException::withMessages(['pickup_time' => self::message()]);
        }
    }

    public static function options(): array
    {
        [$opening, $closing] = self::bounds();
        $periods = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $minutes = [];
            for ($minute = 0; $minute < 60; $minute++) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                if ($time >= $opening && $time <= $closing) {
                    $minutes[] = sprintf('%02d', $minute);
                }
            }
            if ($minutes) {
                $periods[$hour < 12 ? 'AM' : 'PM'][] = [
                    'value' => sprintf('%02d', $hour), 'label' => (string) ($hour % 12 ?: 12), 'minutes' => $minutes,
                ];
            }
        }

        return $periods;
    }
}
