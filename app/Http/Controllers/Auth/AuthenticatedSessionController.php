<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
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
     *
     * A login that started at `/oauth/authorize` leaves the Inertia XHR with a full-page navigation: the
     * authorization endpoint may redirect to an external `redirect_uri`, which an XHR cannot follow.
     */
    public function store(LoginRequest $request): RedirectResponse|SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/oauth/authorize')) {
            $request->session()->forget('url.intended');

            return Inertia::location($intended);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
