<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MenuKey
{
    public function handle(Request $request, Closure $next, string $key)
    {
        $u = $request->user();
        if (!$u) {
            abort(401);
        }

        // cek pakai helper di User
        if (!$u->canSeeMenu($key)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
