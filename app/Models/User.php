<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'status',
        'last_login_at',
        'profile_photo',
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
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Permission Logic
     */
    public function hasPermission($module)
    {
        $permission = $this->permissions()->where('module', $module)->first();

        if (!$permission) {
            return ['no-access']; // Default if not set
        }

        return Permission::normalizePermissionList($permission->permission);
    }

    public function canAccess($module)
    {
        // Admins have full access to everything
        if (strtolower((string) $this->role) === 'admin') {
            return true;
        }

        $permissions = $this->hasPermission($module);
        
        if (is_array($permissions)) {
            return in_array('view', $permissions, true) || in_array('full', $permissions, true);
        }
        
        return $permissions === 'view' || $permissions === 'full';
    }
}
