<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Determine if the user has a specific permission via their assigned role.
     */
    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->is_active || ! $this->role) {
            return false;
        }

        // Load permissions if not already loaded
        if (! $this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        return $this->role->permissions->contains('code', $permissionCode);
    }

    public function hasRole(string|array $roles): bool
    {
        if (! $this->role) {
            return false;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return in_array($this->role->code, $roleList, true);
    }

    public function isAdmin(): bool
    {
        return $this->role?->code === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role?->code === 'supervisor';
    }

    public function isCashier(): bool
    {
        return $this->role?->code === 'cashier';
    }
}
