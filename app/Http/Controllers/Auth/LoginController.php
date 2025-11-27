<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function attempt(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $remember   = $request->boolean('remember');
        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$loginField => $credentials['login'], 'password' => $credentials['password']], $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // role utama via pivot (prioritas admin > warehouse > sales)
            $role = $user->roles()
                ->select('slug', 'home_route')
                ->orderByRaw("FIELD(slug,'admin','warehouse','sales')")
                ->first();

            if (!$role) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Akun belum memiliki role.');
            }

            $fallback = [
                'admin'     => 'admin.dashboard',
                'warehouse' => 'warehouse.dashboard',
                'sales'     => 'sales.dashboard',
            ];
            $target = $role->home_route ?: ($fallback[$role->slug] ?? 'dashboard');

            return redirect()->route($target);
        }

        throw ValidationException::withMessages([
            'login' => __('Nama/email atau password salah.'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login')->with('status', 'Anda telah logout.');
    }
}
