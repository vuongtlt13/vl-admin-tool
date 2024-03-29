<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Traits\JsonResponse;
use Closure;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    use JsonResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if (empty($user)) abort(403);
        $roles = $user->roles;
        $requirePermission = $this->findRequirePermission($request);
        if ($requirePermission === null) {
            if ($request->expectsJson()) {
                return $this->error(__("auth.app.permission_denied"));
            }
            else abort(403);
        }

        if ($roles === null)
            return redirect()->route('models.index');

        foreach ($roles as $role) {
            if ($role->code == Role::SUPER_ADMIN) return $next($request);
            $permissions = $role->permissions;
            foreach ($permissions as $permission) {
                if ($requirePermission == $permission->name) return $next($request);
            }
        }
        if ($request->expectsJson()) {
            return $this->error(__("auth.app.permission_denied"));
        }
        else abort(403);
    }

    private function findRequirePermission(\Illuminate\Http\Request $request)
    {
        return getRouteNameFromRoute($request->route());
    }

    public static function hasRole(User $user, $roles) {
        if (empty($user)) return false;
        $userRoles = $user->roles;
        if ($userRoles === null) return false;

        $roleCodes = $userRoles->map(function (Role $role) {
            return $role->code;
        })->toArray();

        foreach ($roles as $role) {
            if (!in_array($role, $roleCodes)) return false;
        }

        return true;
    }

    public static function hasPermission(User $user, $routeName) {
        if (empty($user)) return false;
        $userRoles = $user->roles;
        if ($userRoles === null) return false;

        /** @var Role $role */
        foreach ($userRoles as $role) {
            if ($role->code == Role::SUPER_ADMIN) return true;
            $permissions = $role->permissions;
            /** @var Permission $permission */
            foreach ($permissions as $permission) {
                if ($permission->name == $routeName) {
                    return true;
                }
            }
        }
        return false;
    }
}
