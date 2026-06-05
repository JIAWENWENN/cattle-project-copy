<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\TaskNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = [];

        if ($user) {
            $permissions = Permission::where('user_id', $user->id)
                ->get(['module', 'permission'])
                ->mapWithKeys(fn ($perm) => [
                    $perm->module => Permission::normalizePermissionList($perm->permission),
                ])
                ->toArray();
        }

        $notificationCount = 0;
        if ($user) {
            $notificationCount = TaskNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();
        }

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user,
                'permissions' => $permissions,
                'notificationCount' => $notificationCount,
            ],
        ];
    }
}
