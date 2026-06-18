<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Blocks suspended (banned) accounts from using any protected endpoint.
// Applied once on the user/vendor route groups — every route inside is covered,
// so we never have to check is_active inside individual controller methods.
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        // is_active exists on users & vendors (admins don't have it, so they pass).
        if ($account && isset($account->is_active) && $account->is_active === false) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
