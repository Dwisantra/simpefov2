<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

class EnsureRequesterRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (UserRole::tryFromMixed($user->level ?? $user->role)?->value !== UserRole::USER->value) {
            return response()->json([
                'message' => 'Hanya pemohon yang dapat mengajukan ticket baru.'
            ], 403);
        }

        return $next($request);
    }
}
