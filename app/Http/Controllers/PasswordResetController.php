<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    /**
     * Default password constant
     */
    const DEFAULT_PASSWORD = 'password';

    /**
     * Reset password to default "password" by NIP
     */
    public function resetToDefault(Request $request)
    {
        $request->validate([
            'nip' => 'required|string',
        ]);

        $user = User::where('nip', $request->nip)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'nip' => ['NIP tidak ditemukan dalam sistem.'],
            ]);
        }

        $user->password = Hash::make(self::DEFAULT_PASSWORD);
        $user->save();

        return back()->with('status', 'reset_success');
    }

    /**
     * Show force change password page
     */
    public function showForceChange()
    {
        return Inertia::render('auth/ForceChangePassword');
    }

    /**
     * Process force change password
     */
    public function processForceChange(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($request->password === self::DEFAULT_PASSWORD) {
            throw ValidationException::withMessages([
                'password' => ['Password baru tidak boleh sama dengan password default.'],
            ]);
        }

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect('/dashboard');
    }
}
