<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) return $next($request);

        if (($user->status ?? 'inactive') !== 'active') {
            Auth::logout();

            // invalidate dulu
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // flash notif
            return redirect()->route('login')->with(
                'blocked',
                'Akun kamu sedang <b>Inactive</b>. Silakan hubungi Admin untuk aktivasi.'
            );
        }

        return $next($request);
    }

}
