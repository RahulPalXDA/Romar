<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
        'email_verified_at',
        'email_verification_status',
        'account_status',
        'role',
        'otp_code',
        'otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'email_verification_status' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Check if user has an admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if user has a vendor role.
     */
    public function isVendor(): bool
    {
        return $this->role === UserRole::VENDOR;
    }

    /**
     * Check if user has a standard user role.
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Check if user has any of the specified roles.
     *
     * @param  list<string|UserRole>  $roles
     */
    public function hasRole(string|UserRole ...$roles): bool
    {
        foreach ($roles as $role) {
            $roleValue = $role instanceof UserRole ? $role->value : $role;
            $currentRole = $this->role instanceof UserRole ? $this->role->value : $this->role;
            if ($currentRole === $roleValue) {
                return true;
            }
        }

        return false;
    }
}
