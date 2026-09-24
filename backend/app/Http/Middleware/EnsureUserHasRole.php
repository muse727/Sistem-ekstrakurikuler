<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', null, 401);
        }

        if (!$user->is_active) {
            return ApiResponse::error('User account is inactive', null, 403);
        }

        $userRoleValue = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        // SUPER_ADMIN has full access
        if ($userRoleValue === UserRole::SUPER_ADMIN->value) {
            return $next($request);
        }

        // Convert passed roles (e.g. ['admin', 'super_admin'])
        $allowedRoles = [];
        foreach ($roles as $role) {
            $allowedRoles = array_merge($allowedRoles, explode(',', $role));
        }

        if (in_array($userRoleValue, $allowedRoles, true)) {
            return $next($request);
        }

        return ApiResponse::error('Unauthorized access for your role', null, 403);
    }
}
