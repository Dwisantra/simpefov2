<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ExpireInactiveTokens
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        $token = $user?->currentAccessToken();
        $timeout = (int) config('sanctum.idle_timeout', 15);

        if ($token && $timeout > 0) {
            $lastActivity = $token->last_used_at ?? $token->created_at ?? now();
            $expirationThreshold = now()->subMinutes($timeout);

            if ($lastActivity && $lastActivity->lte($expirationThreshold)) {
                $token->delete();

                return response()->json([
                    'message' => 'Sesi telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.'
                ], 401);
            }
        }

        $response = $next($request);

        if (
            $token instanceof PersonalAccessToken
            && $token->exists
            && $timeout > 0
        ) {
            $token->forceFill([
                'last_used_at' => now(),
            ])->save();
        }

        return $response;
    }
}
