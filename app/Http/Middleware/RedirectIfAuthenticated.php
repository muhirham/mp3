<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            switch ($user->role) {
                case 'admin':
                    return redirect()->route('admin.dashboard');
                case 'warehouse':
                    return redirect()->route('warehouse.dashboard');
                case 'sales':
                    return redirect()->route('sales.dashboard');
                default:
                    return redirect()->route('login');
            }
        }

        return $next($request);
    }
}