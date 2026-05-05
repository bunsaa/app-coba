<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\PasswordResetController;

class EnsurePasswordChanged
{
    /**
     * Jika user login dengan password default, paksa ganti password.
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && Hash::check(PasswordResetController::DEFAULT_PASSWORD, auth()->user()->password)) {
            // Izinkan akses ke halaman force-change-password, logout, dan captcha
            $allowed = ['force-change-password', 'logout', 'captcha'];

            foreach ($allowed as $path) {
                if ($request->is($path . '*') || $request->is($path)) {
                    return $next($request);
                }
            }

            return redirect('/force-change-password');
        }

        return $next($request);
    }
}
