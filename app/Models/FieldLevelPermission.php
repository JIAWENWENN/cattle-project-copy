<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldLevelPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'field',
        'action',
        'allowed_roles',
        'description',
        'is_active',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all permissions for a specific module
     */
    public static function getByModule($moduleName)
    {
        return static::where('module', $moduleName)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if a role has permission for a specific field action
     */
    public static function hasPermission($module, $field, $action, $userRole)
    {
        $permission = static::where('module', $module)
            ->where('field', $field)
            ->where('action', $action)
            ->where('is_active', true)
            ->first();

        if (!$permission) {
            return false;
        }

        $allowedRoles = $permission->allowed_roles;
        
        // Handle case where allowed_roles might be a JSON string instead of array
        if (is_string($allowedRoles)) {
            $allowedRoles = json_decode($allowedRoles, true);
        }
        
        // Ensure we have an array
        if (!is_array($allowedRoles)) {
            $allowedRoles = [];
        }
        
        return in_array($userRole, $allowedRoles);
    }

    /**
     * Get all available modules with their permissions
     */
    public static function getModulesWithPermissions()
    {
        return static::select('module')
            ->distinct()
            ->where('is_active', true)
            ->orderBy('module')
            ->pluck('module');
    }

    /**
     * Update permission for a specific field
     */
    public static function updatePermission($module, $field, $action, array $roles)
    {
        return static::updateOrCreate(
            [
                'module' => $module,
                'field' => $field,
                'action' => $action,
            ],
            [
                'allowed_roles' => $roles,
                'is_active' => true,
            ]
        );
    }
}
