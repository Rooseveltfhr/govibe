<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Konekte nan panèl la.
 *
 * Pa gen enskripsyon: kont yo kreye ak `php artisan govibe:admin` sou sèvè a.
 */
class AuthController extends Controller
{
    private const MAX_TRIES = 5;

    private const LOCK_SECONDS = 300;

    public function show(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->is_admin === true) {
            return redirect()->route('admin.dashboard');
        }

        return view('core::admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Yon fòm konekte san limit se yon envitasyon pou eseye modpas youn
        // apre lòt. Limit la sou imèl + IP: yon sèl atakan pa ka bloke tout
        // moun nan sèvi ak yon sèl imèl.
        $throttleKey = mb_strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_TRIES)) {
            throw ValidationException::withMessages([
                'email' => __('Trop de tentatives. Réessayez dans :seconds secondes.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOCK_SECONDS);

            // Menm mesaj pou yon imèl ki pa egziste ak pou yon move modpas:
            // sinon fòm nan di ki imèl ki gen yon kont.
            throw ValidationException::withMessages([
                'email' => __('Identifiants incorrects.'),
            ]);
        }

        if (Auth::user()?->is_admin !== true) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => __('Identifiants incorrects.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
