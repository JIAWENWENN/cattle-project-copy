<?php

namespace App\Http\Controllers;

use App\Models\FieldLevelPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class FieldLevelPermissionController extends Controller
{
    /**
     * Display the Field Level Permissions management page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Only admin can access field level permissions
        if (!$user || $user->role !== 'admin') {
            return redirect()->route('access-denied');
        }

        $roles = User::select('role')
            ->distinct()
            ->whereNotNull('role')
            ->orderBy('role')
            ->pluck('role');

        $permissions = FieldLevelPermission::orderBy('module')
            ->orderBy('field')
            ->get()
            ->toArray();

        // Group permissions by module
        $permissionsByModule = [];
        foreach ($permissions as $perm) {
            $module = $perm['module'];
            if (!isset($permissionsByModule[$module])) {
                $permissionsByModule[$module] = [];
            }
            $permissionsByModule[$module][] = $perm;
        }

        return Inertia::render('FieldLevelPermissions', [
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionsByModule' => $permissionsByModule,
        ]);
    }

    /**
     * Update field level permissions
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        // Only admin can update field level permissions
        if (!$user || $user->role !== 'admin') {
            return redirect()->route('access-denied');
        }
        
        $validated = $request->validate([
            'permissions' => 'sometimes|array',
        ]);

        foreach ($validated['permissions'] as $permissionData) {
            FieldLevelPermission::updateOrCreate(
                [
                    'id' => $permissionData['id'] ?? null,
                ],
                [
                    'module' => $permissionData['module'],
                    'field' => $permissionData['field'],
                    'action' => $permissionData['action'],
                    'allowed_roles' => $permissionData['allowed_roles'] ?? [],
                    'description' => $permissionData['description'] ?? null,
                    'is_active' => $permissionData['is_active'] ?? true,
                ]
            );
        }

        return redirect()->back()->with('success', 'Field level permissions updated successfully.');
    }

    /**
     * Check if user has permission for a specific field action
     */
    public static function checkPermission($module, $field, $action)
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Admin always has access
        if ($user->role === 'admin') {
            return true;
        }

        return FieldLevelPermission::hasPermission($module, $field, $action, $user->role);
    }
}
