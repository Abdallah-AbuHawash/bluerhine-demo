<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trivial single-user login — the demo needs a session, not an auth system.
 */
class AuthController extends Controller
{
    public function show(): Response
    {
        // Pre-filling is a local convenience. Once real credentials are set for
        // a hosted demo, the form comes up empty and the hint disappears.
        $prefill = (bool) config('demo.prefill_login');

        return Inertia::render('Auth/Login', [
            'demoEmail' => $prefill ? config('demo.user.email') : '',
            'demoPassword' => $prefill ? config('demo.user.password') : '',
            'showHint' => $prefill,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('estimates.create'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
