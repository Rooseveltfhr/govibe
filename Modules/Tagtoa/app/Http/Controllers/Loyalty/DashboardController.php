<?php

namespace Modules\Tagtoa\App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA Loyalty — dashboard propriétaire (programmes, cartes, recharges, récompenses).
 */
class DashboardController extends Controller
{
    public function __construct(protected LoyaltyCardService $service)
    {
    }

    public function index(): View
    {
        $programs = Program::where('tenant_id', Tenant::id())->withCount('cards')->latest()->paginate(12);

        return view('tagtoa::loyalty.index', compact('programs'));
    }

    public function create(): View
    {
        return view('tagtoa::loyalty.form', ['program' => new Program(), 'vcards' => $this->vcards()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProgram($request);
        $program = new Program($data);
        $program->tenant_id = Tenant::id();
        $program->alias = $data['alias'] ?: Program::generateAlias($data['name']);
        if ($request->hasFile('logo')) {
            $program->logo_path = $request->file('logo')->store('tagtoa/loyalty-logos', 'public');
        }
        $program->save();

        return redirect()->route('tagtoa.loyalty.dashboard.edit', $program->id)->with('success', __('Programme créé.'));
    }

    public function edit(int $id): View
    {
        $program = $this->own($id, ['rewards']);

        return view('tagtoa::loyalty.form', ['program' => $program, 'vcards' => $this->vcards()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $program = $this->own($id);
        $data = $this->validateProgram($request, $program->id);
        $data['alias'] = $data['alias'] ?: $program->alias;
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('tagtoa/loyalty-logos', 'public');
        }
        $program->update($data);

        return back()->with('success', __('Programme mis à jour.'));
    }

    public function cards(int $id): View
    {
        $program = $this->own($id);
        $cards = $program->cards()->latest()->paginate(20);

        return view('tagtoa::loyalty.cards', compact('program', 'cards'));
    }

    public function issueCard(Request $request, int $id): RedirectResponse
    {
        $program = $this->own($id);
        $data = $request->validate([
            'cardholder_name'  => ['required', 'string', 'max:120'],
            'cardholder_phone' => ['nullable', 'string', 'max:40'],
            'cardholder_email' => ['nullable', 'email', 'max:120'],
            'balance'          => ['nullable', 'numeric', 'min:0'],
        ]);

        $res = $this->service->issueCard($program, $data);

        return redirect()->route('tagtoa.loyalty.dashboard.cards', $program->id)
            ->with('success', __('Carte émise.'))
            ->with('new_card', ['number' => $res['card']->formatted_number, 'cvc' => $res['cvc'], 'url' => $res['card']->public_url]);
    }

    public function topUp(Request $request, int $cardId): RedirectResponse
    {
        $card = $this->ownCard($cardId);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'payment_method' => ['nullable', 'string', 'max:40']]);
        $this->service->topUp($card, (float) $data['amount'], ['payment_method' => $data['payment_method'] ?? null]);

        return back()->with('success', __('Carte rechargée.'));
    }

    public function redeem(Request $request, int $cardId): RedirectResponse
    {
        $card = $this->ownCard($cardId);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        try {
            $this->service->redeem($card, (float) $data['amount']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', __('Paiement effectué.'));
    }

    public function storeReward(Request $request, int $id): RedirectResponse
    {
        $program = $this->own($id);
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'points_required' => ['required', 'integer', 'min:1'],
            'discount_value'  => ['nullable', 'numeric', 'min:0'],
            'discount_type'   => ['nullable', 'in:fixed,percent'],
        ]);
        $program->rewards()->create($data + ['is_active' => true]);

        return back()->with('success', __('Récompense ajoutée.'));
    }

    /* helpers */
    protected function own(int $id, array $with = []): Program
    {
        return Program::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function ownCard(int $id): Card
    {
        return Card::whereHas('program', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
    }

    protected function validateProgram(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'          => ['nullable', 'integer'],
            'name'              => ['required', 'string', 'max:160'],
            'alias'             => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_loyalty_programs,alias'.($ignoreId ? ','.$ignoreId : '')],
            'description'       => ['nullable', 'string', 'max:1000'],
            'points_per_dollar' => ['nullable', 'numeric', 'min:0'],
            'dollar_per_point'  => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'max:10'],
            'is_active'         => ['nullable', 'boolean'],
            'logo'              => ['nullable', 'image', 'max:2048'],
        ]);
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
