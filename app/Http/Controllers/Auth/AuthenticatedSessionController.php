<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    private function resolveHomeRoute(?User $user): string
    {
        if (! $user) {
            return route('apps.dashboard', absolute: false);
        }

        if ($user->can('dashboard-data')) {
            return route('apps.dashboard', absolute: false);
        }

        if ($user->can('inventory.receiving.view') || $user->can('inventory.view')) {
            return route('apps.inbound.receiving.index', absolute: false);
        }

        if ($user->can('inventory-reports-access')) {
            return route('apps.reports.inventory.index', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        return redirect()->intended($this->resolveHomeRoute($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|HttpResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()->route('login');
    }
}
