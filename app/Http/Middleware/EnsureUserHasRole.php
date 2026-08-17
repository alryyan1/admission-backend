<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'ليس لديك صلاحية للقيام بهذا الإجراء.');
        }

        return $next($request);
    }
}
