<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ComptePortail;
use App\Models\ConnexionPortail;
use App\Services\RattachementServicesClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private RattachementServicesClient $rattachement) {}

    // ── Connexion ────────────────────────────────────────

    public function formulaireConnexion()
    {
        return view('portail.auth.connexion');
    }

    public function connexion(Request $request)
    {
        $valide = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string',
        ]);

        $cle = $this->cleLimitation($request, $valide['email']);
        $max = (int) config('govibe.portail.tentatives_connexion', 5);

        // Sans limitation, un mot de passe court tombe en quelques heures.
        if (RateLimiter::tooManyAttempts($cle, $max)) {
            $this->journaliser($request, $valide['email'], false, 'trop_essais');

            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans '
                    .ceil(RateLimiter::availableIn($cle) / 60).' minute(s).',
            ]);
        }

        $compte = ComptePortail::where('email', $valide['email'])->first();

        if (! $compte || ! Hash::check($valide['password'], $compte->password)) {
            RateLimiter::hit($cle, 60);
            $this->journaliser($request, $valide['email'], false, $compte ? 'mot_de_passe' : 'inconnu');

            // Même message dans les deux cas : distinguer « compte inconnu » de
            // « mot de passe faux » révélerait quels emails ont un compte.
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if (! $compte->actif) {
            $this->journaliser($request, $valide['email'], false, 'desactive', $compte);

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez GOVIBE.',
            ]);
        }

        RateLimiter::clear($cle);

        auth('client')->login($compte, $request->boolean('memoire'));
        // Contre la fixation de session : l'identifiant change à la connexion.
        $request->session()->regenerate();

        $compte->forceFill([
            'dernier_login_le' => now(),
            'dernier_login_ip' => $request->ip(),
        ])->save();

        $this->journaliser($request, $compte->email, true, null, $compte);

        return redirect()->intended(route('portail.tableau-bord'));
    }

    public function deconnexion(Request $request)
    {
        auth('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portail.connexion');
    }

    // ── Création de compte ───────────────────────────────

    public function formulaireInscription()
    {
        return view('portail.auth.inscription');
    }

    public function inscription(Request $request)
    {
        $valide = $request->validate([
            'nom' => 'required|string|max:150',
            'entreprise' => 'nullable|string|max:200',
            'email' => 'required|email|max:190|unique:comptes_portail,email',
            'telephone' => 'nullable|string|max:40',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Un compte existe déjà pour cette adresse. Connectez-vous.',
        ]);

        $compte = DB::transaction(function () use ($valide) {
            // Un client CRM existant est réutilisé : le portail ne doit pas
            // créer un doublon de quelqu'un que l'équipe suit déjà.
            $client = Client::where('email', $valide['email'])->first();

            if (! $client) {
                $client = Client::create([
                    'reference_number' => 'CL-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                    'name' => $valide['entreprise'] ?: $valide['nom'],
                    // Valeurs imposées par la colonne : renseigner une raison
                    // sociale fait une société, sinon un particulier.
                    'type' => filled($valide['entreprise'] ?? null) ? 'company' : 'individual',
                    'email' => $valide['email'],
                    'phone' => $valide['telephone'] ?? null,
                ]);
            }

            return ComptePortail::create([
                'client_id' => $client->id,
                'nom' => $valide['nom'],
                'email' => $valide['email'],
                'telephone' => $valide['telephone'] ?? null,
                'password' => $valide['password'],
                'jeton_verification' => ComptePortail::genererJeton(),
                'actif' => true,
            ]);
        });

        auth('client')->login($compte);
        $request->session()->regenerate();

        return redirect()->route('portail.verification.attente');
    }

    // ── Vérification de l'adresse ────────────────────────

    public function attenteVerification()
    {
        $compte = auth('client')->user();

        if (! $compte) {
            return redirect()->route('portail.connexion');
        }

        if ($compte->peutVoirSesDonnees()) {
            return redirect()->route('portail.tableau-bord');
        }

        return view('portail.auth.verification', compact('compte'));
    }

    public function verifier(string $jeton)
    {
        $compte = ComptePortail::where('jeton_verification', $jeton)->first();

        if (! $compte) {
            return redirect()->route('portail.connexion')
                ->withErrors(['email' => 'Ce lien de vérification est invalide ou déjà utilisé.']);
        }

        $compte->forceFill([
            'email_verifie_le' => now(),
            'jeton_verification' => null,
        ])->save();

        // L'historique n'est rattaché qu'ici : avant la vérification, rien ne
        // prouve que la personne possède réellement cette adresse.
        $rattaches = $this->rattachement->rattacher($compte);

        auth('client')->login($compte);

        return redirect()->route('portail.tableau-bord')->with(
            'succes',
            $rattaches > 0
                ? "Adresse vérifiée. {$rattaches} élément(s) de votre historique ont été rattachés à votre compte."
                : 'Adresse vérifiée. Bienvenue sur votre espace GOVIBE.'
        );
    }

    // ── Journal ──────────────────────────────────────────

    private function cleLimitation(Request $request, string $email): string
    {
        return 'portail:'.Str::lower($email).'|'.$request->ip();
    }

    private function journaliser(
        Request $request,
        string $email,
        bool $reussie,
        ?string $motif = null,
        ?ComptePortail $compte = null
    ): void {
        ConnexionPortail::create([
            'compte_portail_id' => $compte?->id,
            'email' => $email,
            'reussie' => $reussie,
            'motif' => $motif,
            'ip' => $request->ip(),
            'agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }
}
