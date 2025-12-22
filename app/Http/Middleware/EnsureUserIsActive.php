<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u) {
            return redirect()->route('login');
        }

        // Ganti sesuai kolom user lu:
        // misal: is_active / active / status
        $isActive = true;

        if (isset($u->is_active)) {
            $isActive = (bool) $u->is_active;
        } elseif (isset($u->active)) {
            $isActive = (bool) $u->active;
        } elseif (isset($u->status)) {
            // kalau status string
            $isActive = in_array(strtolower((string)$u->status), ['active','aktif','1','true'], true);
        }

        if (!$isActive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Akun kamu nonaktif.']);
        }

        return $next($request);
    }
}
