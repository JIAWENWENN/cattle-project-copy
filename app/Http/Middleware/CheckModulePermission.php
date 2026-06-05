<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    public function handle(Request $request, Closure $next, $module, $action = null): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        if (!$user->canAccess($module)) {
            return redirect()->route('access-denied');
        }

        if ($action) {
            if (strtolower((string) $user->role) === 'admin') {
                return $next($request);
            }

            $permissions = Permission::normalizePermissionList($user->hasPermission($module));
            $allowed = in_array('full', $permissions, true) || in_array((string) $action, $permissions, true);

            if (!$allowed) {
                return redirect()->route('access-denied');
            }
        }

        return $next($request);
    }
}
