<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case VENDOR = 'vendor';
    case ADMIN = 'admin';

    /**
     * Get all enum values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
