<?php

namespace Modules\Tagtoa\App\Http\Controllers\Stand;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Stand\StandTransferService;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Stand\TransferCode;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA SMART STAND — céder ses stands, ou reprendre ceux d'un autre.
 *
 * Le code de cession n'est affiché QU'UNE FOIS, au retour immédiat de
 * l'émission. Il n'est ni relisible, ni renvoyable : la base n'en garde que
 * l'empreinte. Un écran qui pourrait le réafficher obligerait à le conserver
 * en clair, et la promesse « personne d'autre ne peut le lire » cesserait
 * d'être vraie le jour d'une fuite.
 *
 * Il passe donc par la session, une fois, comme un message éclair.
 */
class TransferController extends Controller
{
    public function __construct(protected StandTransferService $service)
    {
    }

    /** Les cessions de ce commerce : ce qu'il a proposé, ce qu'il peut céder. */
    public function index(): View
    {
        $business = Tenant::id();

        // Les offres périmées rendent leurs stands à l'ouverture de l'écran.
        // C'est le moment où quelqu'un regarde, donc le moment où l'état doit
        // être juste — et cela évite une tâche planifiée de plus à installer.
        $this->service->expireStale($business);

        return view('tagtoa::stand.transfers', [
            'offres'      => $this->service->outgoing($business),
            'cessibles'   => Stand::ofBusiness($business)
                ->where('digital_state', StandState::ACTIVE)
                ->orderBy('public_id')
                ->get(['id', 'public_id', 'location_label']),
            'heures'      => StandTransferService::HEURES,
            'nouveauCode' => session('tagtoa_transfer_code'),
        ]);
    }

    /** Émet une offre sur les stands cochés. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stands'   => ['required', 'array', 'min:1', 'max:'.StandTransferService::MAX_STANDS],
            'stands.*' => ['integer'],
            'note'     => ['nullable', 'string', 'max:160'],
        ]);

        $r = $this->service->offer(Tenant::id(), $data['stands'], [
            'note'       => $data['note'] ?? null,
            'actor_name' => optional(Tenant::user())->name,
            'ip'         => $request->ip(),
        ]);

        if ($r['result'] !== StandTransferService::OK) {
            return back()->with('error', match ($r['result']) {
                StandTransferService::TROP => __('Trop de stands d\'un coup.'),
                default                    => __('Aucun de ces stands ne peut être cédé pour le moment.'),
            });
        }

        // Le clair ne voyage que jusqu'au prochain écran, et une seule fois.
        return redirect()->route('tagtoa.stand.transfer.index')
            ->with('tagtoa_transfer_code', TransferCode::pretty($r['code']))
            ->with('success', trans_choice(
                '{1}:count stand prêt à être cédé.|[2,*]:count stands prêts à être cédés.',
                $r['count'], ['count' => $r['count']]
            ));
    }

    /** Le cédant retire son offre : les stands lui reviennent. */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $r = $this->service->cancel($id, Tenant::id(), [
            'actor_name' => optional(Tenant::user())->name,
            'ip'         => $request->ip(),
        ]);

        return back()->with(
            $r['result'] === StandTransferService::OK ? 'success' : 'error',
            match ($r['result']) {
                StandTransferService::OK           => __('Cession annulée. Vos stands vous sont rendus.'),
                StandTransferService::DEJA_UTILISE => __('Trop tard : cette cession a déjà été acceptée.'),
                StandTransferService::ANNULEE      => __('Cette cession était déjà annulée.'),
                default                            => __('Cession introuvable.'),
            }
        );
    }

    /** L'écran où le repreneur saisit le code qu'on lui a transmis. */
    public function acceptForm(): View
    {
        $business = Tenant::id();

        return view('tagtoa::stand.transfer-accept', [
            'nomCommerce' => Business::whereKey($business)->value('name'),
        ]);
    }

    /** Le repreneur présente le code : les stands rejoignent SON commerce. */
    public function accept(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $r = $this->service->accept($data['code'], Tenant::id(), [
            'actor_name' => optional(Tenant::user())->name,
            'ip'         => $request->ip(),
        ]);

        if ($r['result'] !== StandTransferService::OK) {
            return back()->with('error', match ($r['result']) {
                StandTransferService::EXPIREE      => __('Ce code a expiré. Demandez-en un nouveau au cédant.'),
                StandTransferService::DEJA_UTILISE => __('Ce code a déjà servi.'),
                StandTransferService::ANNULEE      => __('Cette cession a été annulée par son émetteur.'),
                StandTransferService::SOI_MEME     => __('Ces stands sont déjà les vôtres.'),
                // Introuvable et code mal formé donnent le MÊME message : dire
                // « ce code n'existe pas » plutôt que « ce code est invalide »
                // apprendrait à un attaquant à reconnaître une forme valable.
                default                            => __('Code de cession invalide.'),
            });
        }

        return redirect()->route('tagtoa.stand.index')
            ->with('success', trans_choice(
                '{1}:count stand vous a été cédé.|[2,*]:count stands vous ont été cédés.',
                $r['count'], ['count' => $r['count']]
            ));
    }
}
