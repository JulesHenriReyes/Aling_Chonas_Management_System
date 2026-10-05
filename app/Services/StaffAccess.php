<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class StaffAccess
{
    public static function requireOwner(?User $user): User
    {
        $owner = self::require($user);
        if (! $owner->isOwner()) {
            throw new AuthorizationException('This action requires an active Owner.');
        }

        return $owner;
    }

    public static function require(?User $user): User
    {
        $staff = $user?->exists ? User::find($user->id) : null;
        if (! $staff || ! $staff->is_active || ! in_array($staff->role, ['owner', 'assistant'], true)) {
            throw ValidationException::withMessages(['user' => 'This action requires an active Owner or Assistant.']);
        }

        return $staff;
    }
}
