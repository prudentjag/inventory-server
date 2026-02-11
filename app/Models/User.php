<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'password' => 'hashed',
        ];
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Valid roles in the system.
     */
    public const ROLES = [
        'admin',
        'staff',
        'manager',
        'unit_head',
        'stockist',
        'server',
    ];

    /**
     * Check if user is a server.
     */
    public function isServer(): bool
    {
        return $this->role === 'server';
    }

    /**
     * Check if user can manage sales (admin-level access).
     */
    public function canManageSales(): bool
    {
        return in_array($this->role, ['admin', 'stockist']);
    }

    /**
     * Check if user can create sales.
     */
    public function canCreateSales(): bool
    {
        return in_array($this->role, ['admin', 'stockist', 'manager', 'unit_head', 'server', 'staff']);
    }
}
