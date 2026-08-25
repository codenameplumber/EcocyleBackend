<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a surface to one or more roles.
 *
 * A signed-in user hitting the wrong surface is redirected to their own rather
 * than shown a 403 -- an agent who taps a household link should just land in
 * the agent app.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role->value, $roles, true)) {
            return redirect($user->role->home());
        }

        return $next($request);
    }
}
