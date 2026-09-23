<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'phone_number',
    ];

    /**
     * Get customer's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->middle_name} {$this->last_name}")
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Normalize a phone number to standard digits.
     */
    public static function normalizePhoneNumber(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // Convert standard Philippine country code (639xxxxxxxxx) to 09xxxxxxxxx
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * Exact matching rule: phone_number (normalized) + first_name + last_name (+ middle_name when provided).
     * If no exact match exists, create a new customer record.
     */
    public static function findOrCreateMatching(array $data): Customer
    {
        $normalizedPhone = static::normalizePhoneNumber($data['phone_number'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $middleName = !empty($data['middle_name']) ? trim($data['middle_name']) : null;

        $query = static::where('first_name', $firstName)
            ->where('last_name', $lastName);

        if ($middleName !== null) {
            $query->where('middle_name', $middleName);
        } else {
            $query->where(function ($q) {
                $q->whereNull('middle_name')->orWhere('middle_name', '');
            });
        }

        $candidates = $query->get();

        foreach ($candidates as $candidate) {
            if (static::normalizePhoneNumber($candidate->phone_number) === $normalizedPhone) {
                return $candidate;
            }
        }

        return static::create([
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'phone_number' => $normalizedPhone,
        ]);
    }
}
