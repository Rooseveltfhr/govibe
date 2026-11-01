<?php

namespace Modules\Tagtoa\App\Http\Controllers\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Currency\CurrencyRate;
use Modules\Tagtoa\App\Models\Currency\CurrencyRateHistory;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Services\Currency\RateRuleException;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA DEVISES (CUR-1) — « Devises et taux » du commerce COURANT.
 *
 * Le marchand déclare ses devises (USD, $HT…) et leur taux vers sa devise principale.
 * Isolé par commerce : le taux d'un autre commerce est introuvable (404). Jamais supprimé :
 * désactivé (les commandes passées gardent le taux figé sur leurs lignes).
 */
class CurrencyController extends Controller
{
    public function __construct(private CurrencyRateService $rates)
    {
    }

    public function index(): View
    {
        $tenant = $this->tenant();

        return view('tagtoa::currency.index', [
            'rates'   => CurrencyRate::orderBy('base_currency')->orderBy('code')->get(),
            'history' => CurrencyRateHistory::latest('id')->limit(30)->get(),
            'bases'   => $this->bases(),
            'known'   => Money::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'code'          => ['required', 'string', 'max:8'],
            'base_currency' => ['required', 'string', 'in:'.implode(',', $this->bases())],
            'rate'          => ['required', 'numeric', 'gt:0', 'max:'.CurrencyRateService::RATE_MAX],
            'symbol'        => ['nullable', 'string', 'max:8'],
            'decimals'      => ['nullable', 'integer', 'min:0', 'max:3'],
            'confirm_jump'  => ['nullable', 'boolean'],
        ]);

        try {
            $rate = $this->rates->save($tenant, $data['base_currency'], $data, (string) (auth()->id() ?? ''));
        } catch (RateRuleException $e) {
            return back()->withInput()->withErrors(['rate' => $this->message($e->getMessage())])
                ->with('needs_confirm', $e->getMessage() === 'jump_needs_confirmation');
        }

        app(AuditService::class)->log('currency.rate.saved', $rate, $rate->code.' = '.$rate->rate.' '.$rate->base_currency);

        return back()->with('success', __('Taux enregistré : 1 :c = :r :b', ['c' => $rate->code, 'r' => rtrim(rtrim(number_format((float) $rate->rate, 6, '.', ''), '0'), '.'), 'b' => $rate->base_currency]));
    }

    public function toggle(int $id): RedirectResponse
    {
        $this->tenant();
        $rate = CurrencyRate::findOrFail($id);
        $this->rates->toggle($rate, (string) (auth()->id() ?? ''));

        app(AuditService::class)->log($rate->is_active ? 'currency.rate.activated' : 'currency.rate.deactivated', $rate, $rate->code);

        return back()->with('success', $rate->is_active
            ? __(':c réactivée.', ['c' => $rate->code])
            : __(':c désactivée : les articles dans cette devise ne se vendent plus jusqu\'à sa réactivation.', ['c' => $rate->code]));
    }

    /* ----------------------------------------------------------------- */

    private function tenant(): string
    {
        $t = Tenant::id();
        abort_unless($t, 403);

        return $t;
    }

    /** Devises principales possibles : celle du commerce et celles de ses menus. */
    private function bases(): array
    {
        return collect([Tenant::currency()])
            ->merge(Menu::pluck('currency'))
            ->filter()->map(fn ($c) => strtoupper($c))->unique()->values()->all();
    }

    private function message(string $code): string
    {
        return match ($code) {
            'invalid_code'            => __('Code de devise invalide : lettres majuscules ou symboles, 6 au plus (ex. USD, $HT).'),
            'same_as_base'            => __('Une devise ne se convertit pas vers elle-même.'),
            'jump_needs_confirmation' => __('Ce taux change de plus de 50 %. Vérifiez-le, puis cochez « Je confirme ce taux ».'),
            default                   => __('Taux invalide.'),
        };
    }
}
