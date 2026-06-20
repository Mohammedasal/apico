<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLES = [
        'admin' => 'Admin',
        'data_entry' => 'Data Entry',
        'viewer' => 'View Only',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canWriteOperationalData(): bool
    {
        return in_array($this->role, ['admin', 'data_entry'], true);
    }

    public function canViewFinancialReports(): bool
    {
        return in_array($this->role, ['admin', 'viewer'], true);
    }

    public function canManageSystem(): bool
    {
        return $this->role === 'admin';
    }
}
