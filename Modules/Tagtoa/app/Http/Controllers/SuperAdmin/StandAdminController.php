<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandBatch;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Stand\StandAdminService;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — la console du fondateur : le parc entier.
 *
 * Ce que cet écran répond, et que rien ne répondait :
 *   • combien d'objets dorment en entrepôt, combien sont chez des revendeurs ;
 *   • combien sont VENDUS ET MUETS — payés, et jamais activés ;
 *   • où est passé LE stand numéro 57, et qui l'a touché.
 *
 * ⚠️ Cet écran ne montre AUCUN code d'activation, et n'en fabrique aucun. Le
 * fondateur les a, mais ailleurs : dans le fichier de frappe, produit une fois
 * et détruit après tirage. Les remettre dans une page web les rendrait
 * consultables depuis n'importe quel navigateur resté ouvert sur un comptoir.
 */
class StandAdminController extends Controller
{
    /** Une page de fondateur, pas un export. */
    private const PAR_PAGE = 50;

    public function __construct(protected StandAdminService $service)
    {
    }

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'batch' => ['nullable', 'integer'],
            'vue'   => ['nullable', 'string', 'in:muets,jamais_scannes,tous'],
            'q'     => ['nullable', 'string', 'max:24'],
        ]);

        $batchId = $filtres['batch'] ?? null;
        $vue = $filtres['vue'] ?? 'muets';

        return view('tagtoa::superadmin.stands', [
            'parc'    => $this->service->parc($batchId),
            'batches' => StandBatch::orderByDesc('id')->get(['id', 'code', 'quantity', 'range_start', 'range_end', 'recalled_at']),
            'liste'   => $this->liste($vue, $batchId)->paginate(self::PAR_PAGE, [
                // Colonnes NOMMÉES : `select *` ramènerait le haché du secret
                // et le jeton de réservation jusqu'à la vue. `$hidden` les
                // cacherait d'une sérialisation, pas d'un `dd()` ni d'un log.
                'id', 'public_id', 'serial', 'physical_state', 'digital_state',
                'tenant_id', 'holder_type', 'holder_id', 'last_scanned_at', 'claimed_at',
            ])->withQueryString(),
            'vue'      => $vue,
            'batchId'  => $batchId,
            'etatsP'   => StandState::PHYSICAL_LABELS,
            'etatsN'   => StandState::DIGITAL_LABELS,
            'vendeurs' => Reseller::pluck('name', 'id'),
        ]);
    }

    /** La fiche d'un stand et TOUTE son histoire. */
    public function show(string $publicId): View
    {
        $stand = Stand::with('batch')->byPublicId($publicId)->firstOrFail();

        return view('tagtoa::superadmin.stand-show', [
            'stand'     => $stand,
            'histoire'  => $this->service->history($stand),
            'commerce'  => $stand->tenant_id ? Business::whereKey($stand->tenant_id)->first() : null,
            'detenteur' => $stand->holder_type === Stand::HOLDER_RESELLER
                ? Reseller::find($stand->holder_id) : null,
            'etatsP'    => StandState::PHYSICAL_LABELS,
            'etatsN'    => StandState::DIGITAL_LABELS,
        ]);
    }

    /** Perdu, endommagé, retiré, ou rendu au stock. */
    public function physical(Request $request, string $publicId): RedirectResponse
    {
        $data = $request->validate([
            'etat'  => ['required', 'string', 'in:'.implode(',', StandState::PHYSICAL)],
            'motif' => ['nullable', 'string', 'max:200'],
        ]);

        $r = $this->service->markPhysical($publicId, $data['etat'], [
            'actor_name' => optional($request->user())->name,
            'ip'         => $request->ip(),
            'motif'      => $data['motif'] ?? null,
        ]);

        if ($r['result'] !== StandAdminService::OK) {
            return back()->with('error', match ($r['result']) {
                StandAdminService::IMPOSSIBLE => __('Impossible : un stand réclamé par un commerce ne revient pas en stock. Passez par une cession.'),
                default                       => __('Stand introuvable.'),
            });
        }

        app(AuditService::class)->log('stand.physical_changed', null,
            StandId::normalizeId($publicId).' → '.StandState::physicalLabel($data['etat']));

        return back()->with('success', __('État mis à jour : :etat.', [
            'etat' => StandState::physicalLabel($data['etat']),
        ]));
    }

    /** Suspendre, révoquer, ou remettre en service. */
    public function digital(Request $request, string $publicId): RedirectResponse
    {
        $data = $request->validate([
            'etat'  => ['required', 'string', 'in:active,suspended,revoked'],
            'motif' => ['nullable', 'string', 'max:200'],
        ]);

        $r = $this->service->markDigital($publicId, $data['etat'], [
            'actor_name' => optional($request->user())->name,
            'ip'         => $request->ip(),
            'motif'      => $data['motif'] ?? null,
        ]);

        if ($r['result'] !== StandAdminService::OK) {
            return back()->with('error', match ($r['result']) {
                StandAdminService::IMPOSSIBLE => __('Impossible : ce stand n\'appartient à aucun commerce.'),
                default                       => __('Stand introuvable.'),
            });
        }

        app(AuditService::class)->log('stand.digital_changed', null,
            StandId::normalizeId($publicId).' → '.StandState::digitalLabel($data['etat']));

        return back()->with('success', __('Identité mise à jour : :etat.', [
            'etat' => StandState::digitalLabel($data['etat']),
        ]));
    }

    /**
     * La cession forcée — quand le propriétaire a disparu.
     *
     * Le motif n'est PAS décoratif et n'est pas optionnel : c'est le pouvoir le
     * plus dangereux de la plateforme, et la seule chose qu'un contrôle puisse
     * garantir est qu'il soit impossible à nier.
     */
    public function force(Request $request, string $publicId): RedirectResponse
    {
        $data = $request->validate([
            'to_business_id' => ['required', 'string', 'max:64'],
            'motif'          => ['required', 'string', 'min:'.StandAdminService::MOTIF_MIN, 'max:200'],
        ]);

        $r = $this->service->forceTransfer($publicId, $data['to_business_id'], $data['motif'], [
            'actor_name' => optional($request->user())->name,
            'ip'         => $request->ip(),
        ]);

        if ($r['result'] !== StandAdminService::OK) {
            return back()->with('error', match ($r['result']) {
                StandAdminService::SANS_MOTIF    => __('Un motif écrit est obligatoire : au moins :n caractères.', ['n' => StandAdminService::MOTIF_MIN]),
                StandAdminService::MEME_COMMERCE => __('Ce stand appartient déjà à ce commerce.'),
                StandAdminService::IMPOSSIBLE    => __('Cet objet n\'est plus en circulation : perdu, endommagé ou retiré.'),
                default                          => __('Stand introuvable.'),
            });
        }

        // Deux traces, et c'est voulu : le journal du stand suit l'OBJET, la
        // ligne d'audit suit la PLATEFORME. Chercher « qu'a fait le fondateur
        // cette semaine » ne doit pas obliger à parcourir dix mille stands.
        app(AuditService::class)->log('stand.force_transferred', null,
            StandId::normalizeId($publicId).' → '.$data['to_business_id'].' — '.$data['motif']);

        return back()->with('success', __('Stand rattaché à :nom.', [
            'nom' => Business::whereKey($data['to_business_id'])->value('name') ?: $data['to_business_id'],
        ]));
    }

    /* ---------------- interne ---------------- */

    private function liste(string $vue, ?int $batchId)
    {
        $q = match ($vue) {
            'muets' => $this->service->muets($batchId),
            'jamais_scannes' => Stand::query()
                ->where('digital_state', StandState::ACTIVE)
                ->whereNull('last_scanned_at')
                ->when($batchId, fn ($x) => $x->where('batch_id', $batchId)),
            default => Stand::query()->when($batchId, fn ($x) => $x->where('batch_id', $batchId)),
        };

        return $q->orderBy('serial');
    }
}
