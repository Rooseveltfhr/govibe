<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ERPAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('erp.dashboard');
        return view('erp.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => "L'email est obligatoire.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (!Auth::user()->is_admin) {
                Auth::logout();
                return back()->withErrors(['email' => 'Accès refusé. Contact un administrateur.']);
            }
            $request->session()->regenerate();
            return redirect()->route('erp.dashboard');
        }

        return back()->withErrors(['email' => 'Identifiants incorrects.'])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('erp.login');
    }
}
