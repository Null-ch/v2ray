<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class CockpitController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('cockpit')->check()) {
            return redirect()->route('cockpit.dashboard');
        }
        
        return view('cockpit.login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('username', 'password');

        if (Auth::guard('cockpit')->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('cockpit.dashboard');
        }

        return back()->withErrors([
            'credentials' => 'Неверный логин или пароль.',
        ])->withInput($request->only('username'));
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('cockpit')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('cockpit.login');
    }

    /**
     * Show the dashboard.
     */
    public function dashboard(): View
    {
        return view('cockpit.dashboard');
    }
}

