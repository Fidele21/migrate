<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sign in and sign out.
 *
 * There is deliberately no public registration. Accounts are created
 * by an Administrator, who assigns the role and district. An inspector
 * choosing their own authority level would defeat the whole access
 * control model.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Rate limit: five attempts per email per minute.
        $key = 'login:' . strtolower($credentials['email']) . '|' . $request->ip();
        if (cache()->get($key, 0) >= 5) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please wait a minute and try again.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            cache()->put($key, cache()->get($key, 0) + 1, now()->addMinute());
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact the administrator.',
            ]);
        }

        cache()->forget($key);
        $request->session()->regenerate();

        Auth::user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
