<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConnexionPersonnelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ERPAuthController extends Controller
{
    public function __construct(private ConnexionPersonnelService $securite)
    {
    }

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

        // Même clé et même journal que /admin/login : les deux écrans
        // mènent au même compte, un essai sur l'un compte pour l'autre.
        $this->securite->verifierLimite($request, $credentials['email'], 'erp');

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->securite->echec($request, $credentials['email'], 'erp', $user ? 'mot_de_passe' : 'inconnu', $user);

            return back()->withErrors(['email' => 'Identifiants incorrects.'])->withInput($request->only('email'));
        }

        if (! $user->is_admin) {
            $this->securite->echec($request, $credentials['email'], 'erp', 'pas_admin', $user);
            return back()->withErrors(['email' => 'Accès refusé. Contact un administrateur.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->securite->reussite($request, $credentials['email'], 'erp', $user);

        return redirect()->route('erp.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('erp.login');
    }
}
