<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TouchTokenExpiration
{
    /**
     * Extend the current access token's expiration on every authenticated
     * request, so a token only expires after "sanctum.token_ttl" minutes of
     * genuine inactivity rather than a fixed time since login.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->expires_at !== null) {
            $token->forceFill([
                'expires_at' => now()->addMinutes(config('sanctum.token_ttl')),
            ])->save();
        }

        return $response;
    }
}
