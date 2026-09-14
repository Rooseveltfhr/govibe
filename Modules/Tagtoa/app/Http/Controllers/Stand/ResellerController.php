<?php

namespace Modules\Tagtoa\App\Http\Controllers\Stand;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Stand\ResellerService;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA SMART STAND — la console du revendeur.
 *
 * ── CE QUE CET ÉCRAN NE MONTRE JAMAIS ───────────────────────────────────
 *
 * Aucun code d'activation. Aucun. C'est la contrainte qui a dessiné l'écran,
 * pas une précaution ajoutée après coup.
 *
 * Un code d'activation vit sous un panneau à gratter, et c'est justement son
 * point : celui qui tient l'objet est le seul à pouvoir le lire. Un revendeur
 * qui verrait les codes de son stock pourrait réclamer cinquante stands à son
 * nom, ou vendre le même stand deux fois — et le second acheteur découvrirait
 * à l'activation que son objet appartient déjà à quelqu'un.
 *
 * Le modèle `Stand` cache déjà `secret_hash`, mais on ne s'appuie pas là-dessus
 * seul : aucune requête d'ici ne le sélectionne, et un test le vérifie sur le
 * HTML rendu.
 *
 * ── Le cloisonnement est EXPLICITE ──────────────────────────────────────
 *
 * `Stand` n'a pas de portée automatique (un stand non réclamé n'appartient à
 * aucun commerce). Chaque requête passe donc par `heldBy()` en toutes lettres :
 * une requête qui l'oublierait montrerait à un revendeur le stock de tous les
 * autres — c'est-à-dire la carte complète du réseau de distribution de TAGTOA.
 */
class ResellerController extends Controller
{
    public function __construct(protected ResellerService $service)
    {
    }

    public function index(Request $request): View
    {
        $reseller = $this->moi();

        $filtre = $request->validate(['etat' => ['nullable', 'string'], 'q' => ['nullable', 'string', 'max:40']]);

        $query = Stand::heldBy($reseller->id)->orderBy('serial');

        if (($filtre['etat'] ?? null) === 'vendus') {
            $query->where('physical_state', StandState::SOLD);
        } elseif (($filtre['etat'] ?? null) === 'stock' || ! isset($filtre['etat'])) {
            $query->where('physical_state', StandState::ALLOCATED);
        }

        if ($terme = trim((string) ($filtre['q'] ?? ''))) {
            $query->where('public_id', 'like', '%'.strtoupper($terme).'%');
        }

        return view('tagtoa::stand.reseller', [
            'reseller' => $reseller,
            // Colonnes NOMMÉES, jamais `*` : c'est ce qui garantit qu'aucune
            // requête d'ici ne ramène le haché du secret, même si quelqu'un
            // retirait un jour le `$hidden` du modèle.
            'stands'   => $query->paginate(50, [
                'id', 'public_id', 'serial', 'physical_state', 'digital_state', 'batch_id',
            ])->withQueryString(),
            'compte'   => $this->service->inventory($reseller),
            'filtre'   => $filtre,
        ]);
    }

    /** Le revendeur déclare une vente. L'objet part ; l'identité reste. */
    public function sell(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'public_id'   => ['required', 'string', 'max:24'],
            'buyer_name'  => ['nullable', 'string', 'max:120'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $resultat = $this->service->declareSale($this->moi(), $data['public_id'], [
            'buyer_name'  => $data['buyer_name'] ?? null,
            'buyer_phone' => $data['buyer_phone'] ?? null,
            'ip'          => $request->ip(),
        ]);

        return match ($resultat['result']) {
            ResellerService::OK => back()->with('success',
                __('Vente enregistrée : :id. Le commerçant l\'activera en grattant son code.',
                    ['id' => $resultat['stand']->public_id])),
            ResellerService::DEJA_VENDU => back()->with('success',
                __('Ce stand était déjà déclaré vendu.')),
            // On ne dit PAS « ce stand appartient à un autre revendeur » :
            // ce serait confirmer, numéro par numéro, ce que détient le réseau.
            default => back()->with('error',
                __('Ce stand n\'est pas dans votre stock.')),
        };
    }

    /** L'historique d'un stand du revendeur — sans aucun secret. */
    public function history(int $id): View
    {
        $reseller = $this->moi();

        $stand = Stand::heldBy($reseller->id)->whereKey($id)
            ->firstOrFail(['id', 'public_id', 'serial', 'physical_state', 'digital_state']);

        return view('tagtoa::stand.reseller-history', [
            'stand'  => $stand,
            'events' => StandEvent::where('stand_id', $stand->id)->orderBy('id')->get(),
        ]);
    }

    /* ---------------- interne ---------------- */

    /**
     * Le revendeur que je suis, ou 404.
     *
     * Un commerce qui n'est pas revendeur, ou qui ne l'est plus, ne doit pas
     * voir un écran vide : il ne doit pas voir l'écran du tout. Un compte
     * désactivé qui garderait l'accès continuerait de déclarer des ventes.
     */
    private function moi(): Reseller
    {
        $r = Reseller::forBusiness(Tenant::id());
        abort_unless($r, 404);

        return $r;
    }
}
