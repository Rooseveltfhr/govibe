<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConnexionPersonnelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private ConnexionPersonnelService $securite)
    {
    }

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'L\'email est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        // Sans limitation, un mot de passe court tombe en quelques heures —
        // et ce compte ouvre l'Academy ET l'ERP.
        $this->securite->verifierLimite($request, $credentials['email'], 'admin');

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->securite->echec($request, $credentials['email'], 'admin', $user ? 'mot_de_passe' : 'inconnu', $user);

            // Même message dans les deux cas : distinguer « compte inconnu »
            // de « mot de passe faux » révélerait quels emails ont un compte.
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->withInput($request->only('email'));
        }

        if (! $user->is_admin) {
            $this->securite->echec($request, $credentials['email'], 'admin', 'pas_admin', $user);
            return back()->withErrors(['email' => 'Accès refusé. Vous n\'êtes pas administrateur.']);
        }

        Auth::login($user, $request->boolean('remember'));
        // Contre la fixation de session : l'identifiant change à la connexion.
        $request->session()->regenerate();
        $this->securite->reussite($request, $credentials['email'], 'admin', $user);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
