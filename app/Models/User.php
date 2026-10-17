<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable;

    /**
     * Grantable, additive capabilities beyond what a role implies by default.
     * Every user starts without them; an admin opts a user in from settings.
     */
    public const PERMISSION_ADD_DIRECT_DEPOSIT = 'add_direct_deposit';

    /**
     * @var array<int, string>
     */
    public const AVAILABLE_PERMISSIONS = [
        self::PERMISSION_ADD_DIRECT_DEPOSIT,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'allowed_pages',
        'permissions',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        /** Sidebar/route paths this user may access; null means unrestricted (governed by role only). */
        'allowed_pages' => 'array',
        /** Granted additive permission slugs (see AVAILABLE_PERMISSIONS); null/missing means none. */
        'permissions' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'username', 'role', 'allowed_pages', 'permissions', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
