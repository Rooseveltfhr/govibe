<?php

namespace App\Services;

use App\Models\ConnexionAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Garde de connexion partagée entre /admin/login et /erp/login.
 *
 * Les deux écrans mènent au même compte (User::is_admin) : une clé de
 * limitation par email + IP commune leur est donc appliquée, et journalisée
 * dans la même table — sinon un attaquant qui alterne entre les deux portes
 * doublerait le débit de tentatives qui lui est autorisé. Même principe que
 * Portail\AuthController pour le compte client, avec sa propre table
 * (connexions_admin) puisque ce sont deux univers de comptes distincts.
 */
class ConnexionPersonnelService
{
    private function cle(string $email, string $ip): string
    {
        return 'personnel:'.Str::lower($email).'|'.$ip;
    }

    /**
     * Bloque la tentative si le couple email+IP a déjà trop échoué.
     *
     * @throws ValidationException
     */
    public function verifierLimite(Request $request, string $email, string $surface): void
    {
        $cle = $this->cle($email, $request->ip());
        $max = (int) config('govibe.personnel.tentatives_connexion', 5);

        if (RateLimiter::tooManyAttempts($cle, $max)) {
            $this->journaliser($request, $email, $surface, false, 'trop_essais');

            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans '
                    .ceil(RateLimiter::availableIn($cle) / 60).' minute(s).',
            ]);
        }
    }

    public function echec(Request $request, string $email, string $surface, string $motif, ?User $user = null): void
    {
        RateLimiter::hit($this->cle($email, $request->ip()), 60);
        $this->journaliser($request, $email, $surface, false, $motif, $user);
    }

    public function reussite(Request $request, string $email, string $surface, User $user): void
    {
        RateLimiter::clear($this->cle($email, $request->ip()));
        $this->journaliser($request, $email, $surface, true, null, $user);
    }

    private function journaliser(
        Request $request,
        string $email,
        string $surface,
        bool $reussie,
        ?string $motif = null,
        ?User $user = null
    ): void {
        ConnexionAdmin::create([
            'user_id' => $user?->id,
            'surface' => $surface,
            'email' => $email,
            'reussie' => $reussie,
            'motif' => $motif,
            'ip' => $request->ip(),
            'agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }
}
