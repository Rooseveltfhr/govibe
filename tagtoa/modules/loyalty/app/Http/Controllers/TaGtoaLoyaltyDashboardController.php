<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaLoyaltyCard;
use App\Models\TaGtoaLoyaltyProgram;
use App\Models\TaGtoaLoyaltyReward;
use App\Services\LoyaltyCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TAGTOA LOYALTY — dashboard propriétaire.
 *
 * Programmes scopés au tenant via BelongsToTenant sur TaGtoaLoyaltyProgram.
 */
class TaGtoaLoyaltyDashboardController extends Controller
{
    public function __construct(protected LoyaltyCardService $service)
    {
    }

    /* ---------------------------------------------------------------- Programs */

    public function index(): View
    {
        $programs = TaGtoaLoyaltyProgram::withCount('cards')->latest()->paginate(15);

        return view('tagtoa.loyalty.dashboard.index', compact('programs'));
    }

    public function create(): View
    {
        return view('tagtoa.loyalty.dashboard.form', [
            'program' => new TaGtoaLoyaltyProgram(),
            'vcards'  => $this->ownerVcards(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProgram($request);

        $program = new TaGtoaLoyaltyProgram($data);
        $program->tenant_id = getLogInTenantId();
        $program->alias     = $data['alias'] ?: TaGtoaLoyaltyProgram::generateAlias($data['name']);
        $program->save();

        if ($request->hasFile('logo')) {
            $program->addMediaFromRequest('logo')->toMediaCollection('program-logo');
        }

        return redirect()
            ->route('tagtoa.loyalty.dashboard.edit', $program->id)
            ->with('success', __('Programme de fidélité créé.'));
    }

    public function edit(int $id): View
    {
        $program = TaGtoaLoyaltyProgram::with(['rewards', 'media'])->findOrFail($id);

        return view('tagtoa.loyalty.dashboard.form', [
            'program' => $program,
            'vcards'  => $this->ownerVcards(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $program = TaGtoaLoyaltyProgram::findOrFail($id);
        $data    = $this->validateProgram($request, $program->id);

        $data['alias'] = $data['alias'] ?: $program->alias;
        $program->update($data);

        if ($request->hasFile('logo')) {
            $program->clearMediaCollection('program-logo');
            $program->addMediaFromRequest('logo')->toMediaCollection('program-logo');
        }

        return back()->with('success', __('Programme mis à jour.'));
    }

    /* ------------------------------------------------------------------ Cards */

    public function cards(int $programId): View
    {
        $program = TaGtoaLoyaltyProgram::findOrFail($programId);
        $cards   = $program->cards()->latest()->paginate(20);

        return view('tagtoa.loyalty.dashboard.cards', compact('program', 'cards'));
    }

    public function issueCard(Request $request, int $programId): RedirectResponse
    {
        $program = TaGtoaLoyaltyProgram::findOrFail($programId);

        $data = $request->validate([
            'cardholder_name'  => ['required', 'string', 'max:120'],
            'cardholder_phone' => ['nullable', 'string', 'max:40'],
            'cardholder_email' => ['nullable', 'email', 'max:120'],
            'balance'          => ['nullable', 'numeric', 'min:0'],
            'delivery_type'    => ['nullable', 'integer', 'in:0,1,2'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->service->issueCard($program, $data);

        // Le CVC clair n'apparaît qu'une seule fois (flash session).
        return redirect()
            ->route('tagtoa.loyalty.dashboard.cards', $program->id)
            ->with('success', __('Carte émise.'))
            ->with('new_card', [
                'number' => $result['card']->formatted_number,
                'cvc'    => $result['cvc'],
                'url'    => $result['card']->public_url,
            ]);
    }

    public function topUp(Request $request, int $cardId): RedirectResponse
    {
        $card = $this->findOwnedCard($cardId);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'reference'      => ['nullable', 'string', 'max:120'],
        ]);

        $this->service->topUp($card, (float) $data['amount'], [
            'payment_method' => $data['payment_method'] ?? null,
            'reference'      => $data['reference'] ?? null,
        ]);

        return back()->with('success', __('Carte rechargée.'));
    }

    public function redeem(Request $request, int $cardId): RedirectResponse
    {
        $card = $this->findOwnedCard($cardId);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->service->redeem($card, (float) $data['amount'], [
                'reference' => $data['reference'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', __('Paiement effectué depuis la carte.'));
    }

    public function setStatus(Request $request, int $cardId): RedirectResponse
    {
        $card = $this->findOwnedCard($cardId);
        $request->validate(['status' => ['required', 'integer', 'in:0,1,2']]);
        $card->update(['status' => (int) $request->input('status')]);

        return back()->with('success', __('Statut mis à jour.'));
    }

    /* ---------------------------------------------------------------- Rewards */

    public function storeReward(Request $request, int $programId): RedirectResponse
    {
        $program = TaGtoaLoyaltyProgram::findOrFail($programId);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:500'],
            'points_required' => ['required', 'integer', 'min:1'],
            'discount_value'  => ['nullable', 'numeric', 'min:0'],
            'discount_type'   => ['nullable', 'in:fixed,percent'],
        ]);

        $program->rewards()->create($data + ['is_active' => true]);

        return back()->with('success', __('Récompense ajoutée.'));
    }

    public function destroyReward(int $rewardId): RedirectResponse
    {
        TaGtoaLoyaltyReward::whereHas('program')->findOrFail($rewardId)->delete();

        return back()->with('success', __('Récompense supprimée.'));
    }

    /* ----------------------------------------------------------------- Helpers */

    protected function findOwnedCard(int $cardId): TaGtoaLoyaltyCard
    {
        // whereHas('program') applique le scope tenant du programme.
        return TaGtoaLoyaltyCard::whereHas('program')->findOrFail($cardId);
    }

    protected function validateProgram(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'          => ['nullable', 'integer', 'exists:vcards,id'],
            'name'              => ['required', 'string', 'max:160'],
            'alias'             => [
                'nullable', 'string', 'max:120', 'alpha_dash',
                'unique:tagtoa_loyalty_programs,alias' . ($ignoreId ? ',' . $ignoreId : ''),
            ],
            'description'       => ['nullable', 'string', 'max:1000'],
            'points_per_dollar' => ['nullable', 'numeric', 'min:0'],
            'dollar_per_point'  => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'max:10'],
            'is_active'         => ['nullable', 'boolean'],
            'logo'              => ['nullable', 'image', 'max:2048'],
        ]);
    }

    protected function ownerVcards()
    {
        return \App\Models\Vcard::query()->orderBy('name')->get(['id', 'name']);
    }
}
