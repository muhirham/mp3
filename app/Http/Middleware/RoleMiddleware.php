<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$slugs)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // pakai helper dari model User
        if (!$user->hasRole($slugs)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
