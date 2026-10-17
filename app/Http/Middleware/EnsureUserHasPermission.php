<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission($permission)) {
            abort(Response::HTTP_FORBIDDEN, 'ليس لديك صلاحية للقيام بهذا الإجراء.');
        }

        return $next($request);
    }
}
