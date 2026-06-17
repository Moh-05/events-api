<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminRole
{
    // Usage in routes: role:super_admin  OR  role:super_admin,support
    // Accepts a comma-separated list and allows the admin if their role is any of them.
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $admin = $request->user();

        if (!$admin || !in_array($admin->role, $roles, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized — insufficient role',
            ], 403);
        }

        return $next($request);
    }
}
