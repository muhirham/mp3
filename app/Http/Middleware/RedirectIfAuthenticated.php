<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // kalau belum login, biarin akses /login
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // ===== safety: kalau user nonaktif, logout & tampilkan login (stop loop) =====
        $isActive = true;
        if (isset($user->is_active)) {
            $isActive = (bool) $user->is_active;
        } elseif (isset($user->active)) {
            $isActive = (bool) $user->active;
        } elseif (isset($user->status)) {
            $isActive = in_array(strtolower((string) $user->status), ['active','aktif','1','true'], true);
        }

        if (!$isActive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $next($request); // biar halaman login kebuka
        }

        // ===== FIX UTAMA: jangan pakai $user->role, pakai pivot roles =====
        // paling aman: lempar ke route dashboard, dashboard lu yg nentuin home_route
        if (Route::has('dashboard')) {
            return redirect()->route('dashboard');
        }

        // fallback kalau route dashboard ga ada
        return redirect('/dashboard');
    }
}
