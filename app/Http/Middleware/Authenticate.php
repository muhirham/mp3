<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    public function handle(Request $request, Closure $next, ...$slugs)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = $request->user(); // << WAJIB ADA

        if (!empty($slugs)) {
            $ok = $user->roles()->whereIn('slug', $slugs)->exists()
                || in_array(($user->role ?? ''), $slugs, true);

            abort_unless($ok, 403, 'Unauthorized');
        }

        return $next($request);
    }
}
