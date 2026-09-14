<?php

namespace Modules\Tagtoa\App\Http\Controllers\Stand;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Stand\StandClaimService;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Support\Stand\StandId;

/**
 * TAGTOA SMART STAND — ce qui se passe quand on scanne.
 *
 * Route PUBLIQUE, sans authentification : c'est un client attablé qui approche
 * son téléphone, pas un marchand connecté.
 *
 * La règle qui gouverne cet écran : il ne doit JAMAIS ressembler à une panne.
 * Un stand est posé sur une table, devant quelqu'un qui a faim. Une erreur 404
 * ou une page de paiement lui feraient reposer son téléphone — et c'est le
 * commerçant qui en paierait le prix, pas nous.
 */
class StandPublicController extends Controller
{
    public function __construct(
        protected StandResolver $resolver,
        protected StandClaimService $claims,
    ) {
    }

    /**
     * L'aiguillage. La requête la plus fréquente de toute la plateforme.
     *
     * Servie depuis le cache : sans lui, une heure de pointe dans mille
     * restaurants tomberait simultanément sur la base.
     */
    public function show(string $standId): View|RedirectResponse
    {
        $destination = $this->resolver->resolve($standId);

        if ($destination['go'] === StandResolver::GO_TARGET) {
            // Redirection directe, sans page intermédiaire : chaque saut coûte
            // une seconde sur une connexion faible.
            return redirect()->away($destination['url']);
        }

        return view('tagtoa::stand.show', [
            'go'       => $destination['go'],
            'standId'  => StandId::normalizeId($standId),
            'label'    => $destination['label'],
        ]);
    }

    /**
     * L'état public d'un stand.
     *
     * Ne révèle NI le commerce, NI le lot, NI l'emplacement : cette route est
     * ouverte, et un attaquant qui balaie des identifiants ne doit rien
     * apprendre de plus que « activable ou non ».
     */
    public function status(string $standId): JsonResponse
    {
        $destination = $this->resolver->resolve($standId);

        return response()->json([
            'stand'       => StandId::normalizeId($standId),
            'activatable' => $destination['go'] === StandResolver::GO_ACTIVATE,
            'active'      => $destination['go'] === StandResolver::GO_TARGET,
        ]);
    }

    /**
     * Vérifie le code gratté. AUCUN compte requis.
     *
     * C'est l'inversion qui compte : faire créer un compte à quelqu'un pour lui
     * annoncer ensuite que son code est illisible est la façon la plus sûre de
     * le perdre. Le code se vérifie d'abord ; il n'est consommé qu'à la fin.
     */
    public function verify(Request $request, string $standId): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $resultat = $this->claims->verify($standId, $data['code'], [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($resultat['result'] !== StandClaimService::OK) {
            return back()->withErrors(['code' => $this->message($resultat['result'])]);
        }

        // Le jeton vit en session : il ne traverse jamais une URL, où il
        // finirait dans un historique de navigation ou un journal de serveur.
        session([$this->sessionKey($standId) => $resultat['token']]);

        return redirect()->route('tagtoa.stand.claim.form', StandId::normalizeId($standId));
    }

    /** L'écran de réclamation. Exige un compte : c'est lui qui deviendra propriétaire. */
    public function claimForm(Request $request, string $standId): View|RedirectResponse
    {
        if (! $request->session()->has($this->sessionKey($standId))) {
            // Arrivé ici sans avoir vérifié son code : on le renvoie au début
            // plutôt que de lui montrer un formulaire qui échouera.
            return redirect()->route('tagtoa.stand.show', StandId::normalizeId($standId));
        }

        return view('tagtoa::stand.claim', ['standId' => StandId::normalizeId($standId)]);
    }

    /** Consomme la réservation : le stand devient celui du commerce. */
    public function claim(Request $request, string $standId): RedirectResponse
    {
        $data = $request->validate([
            'location_label' => ['nullable', 'string', 'max:60'],
        ]);

        $token = $request->session()->get($this->sessionKey($standId));

        $resultat = $this->claims->claim($standId, $token, \Modules\Tagtoa\App\Support\Tenant::id(), [
            'location_label' => $data['location_label'] ?? null,
            'actor_name'     => optional(\Modules\Tagtoa\App\Support\Tenant::user())->name,
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        $request->session()->forget($this->sessionKey($standId));

        if ($resultat['result'] !== StandClaimService::OK) {
            return redirect()->route('tagtoa.stand.show', StandId::normalizeId($standId))
                ->withErrors(['code' => $this->message($resultat['result'])]);
        }

        return redirect()->route('tagtoa.stand.index')
            ->with('success', __('Le stand :id est à vous.', ['id' => $resultat['stand']->public_id]));
    }

    /**
     * Le jeton de réservation, en session, par stand.
     *
     * Par stand et non global : quelqu'un qui active quarante tables enchaîne
     * les vérifications, et un jeton unique écraserait le précédent.
     */
    private function sessionKey(string $standId): string
    {
        return 'tagtoa_stand_claim:'.StandId::normalizeId($standId);
    }

    /** Ce que la personne lit. Dire ce qui s'est passé ET quoi faire. */
    private function message(string $result): string
    {
        return match ($result) {
            StandClaimService::BAD_CODE  => __('Ce code ne correspond pas. Vérifiez les caractères sous le film gratté.'),
            StandClaimService::NOT_FOUND => __('Ce numéro de stand n\'existe pas. Vérifiez ce qui est imprimé sur le socle.'),
            StandClaimService::NOT_OPEN  => __('Ce stand est déjà activé, ou n\'est plus utilisable. Contactez-nous si c\'est le vôtre.'),
            StandClaimService::TOO_MANY  => __('Trop d\'essais sur ce stand. Réessayez dans une heure.'),
            StandClaimService::RESERVED  => __('Une activation est déjà en cours sur ce stand. Réessayez dans quinze minutes.'),
            default                      => __('Activation impossible pour le moment.'),
        };
    }
}
