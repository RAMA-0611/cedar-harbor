<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $allowed = array_map('strtoupper', $roles);

        if (! in_array(strtoupper((string) $user->role), $allowed, true)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $next($request);
    }
}
