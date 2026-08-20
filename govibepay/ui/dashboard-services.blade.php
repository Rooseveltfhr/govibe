{{--
    GVPA-SERVICES — bloc « Mes services » du tableau de bord utilisateur.

    Injecté juste après @section('content') de resources/views/user/dashboard.blade.php
    par le mode « user-ui » du workflow govibepay-merge.

    RÈGLES TENUES ICI :
      · Aucune route n'est définie ni modifiée — seules les routes existantes sont
        appelées, et chacune est protégée par Route::has() pour qu'une installation
        sans ce module n'entraîne jamais de RouteNotFoundException.
      · Aucune URL n'est réécrite.
      · Les données viennent du helper userActiveCardData(), déjà utilisé par
        DashboardController::index(), qui normalise les cinq fournisseurs de carte
        (Stripe, Sudo, Strowallet, Cardyfie, Flutterwave).
      · Pas de fonction fléchée ni de tableau dans une directive Blade : toute la
        logique est dans un bloc @php, les classes sont nommées complètement.
--}}
@php
    $gvpCards       = [];
    $gvpCardCount   = 0;
    $gvpCardBalance = 0;
    try {
        $gvpCardData    = userActiveCardData();
        $gvpCards       = $gvpCardData['total_cards'] ?? [];
        $gvpCardCount   = $gvpCardData['active_cards'] ?? 0;
        $gvpCardBalance = $gvpCardData['total_balance'] ?? 0;
    } catch (\Throwable $gvpE) {
        $gvpCards = [];
    }

    $gvpFirstCard = null;
    foreach ($gvpCards as $gvpOne) {
        $gvpFirstCard = $gvpOne;
        break;
    }

    $gvpUrlCards   = \Illuminate\Support\Facades\Route::has('user.virtual.card.index') ? route('user.virtual.card.index') : null;
    $gvpUrlCreate  = \Illuminate\Support\Facades\Route::has('user.virtual.card.create.page') ? route('user.virtual.card.create.page') : null;
    $gvpUrlTopup   = \Illuminate\Support\Facades\Route::has('user.mobile.topup.index') ? route('user.mobile.topup.index') : null;
    $gvpUrlPaylink = \Illuminate\Support\Facades\Route::has('user.payment-link.index') ? route('user.payment-link.index') : null;

    $gvpUrlFund = null;
    if ($gvpFirstCard && \Illuminate\Support\Facades\Route::has('user.virtual.card.fund.page')) {
        $gvpUrlFund = route('user.virtual.card.fund.page', $gvpFirstCard->id);
    }

    $gvpCurrency = '';
    if (isset($baseCurrency) && $baseCurrency) {
        $gvpCurrency = $baseCurrency->code ?? '';
    }
@endphp

<div class="gvps">

    <div class="gvps-head">
        <h4 class="gvps-title">{{ __('Mes services') }}</h4>
        @if ($gvpUrlCards)
            <a href="{{ $gvpUrlCards }}" class="gvps-all">{{ __('Tout voir') }}</a>
        @endif
    </div>

    {{-- ── CARTES VIRTUELLES ───────────────────────────────────────────── --}}
    <div class="gvps-rail">
        @forelse ($gvpCards as $gvpCard)
            @php
                $gvpRaw = $gvpCard->card_number ?? $gvpCard->card_pan ?? $gvpCard->masked_card ?? '';
                $gvpDigits = preg_replace('/\D/', '', (string) $gvpRaw);
                $gvpLast4 = $gvpDigits !== '' ? substr($gvpDigits, -4) : '••••';
                $gvpHolder = $gvpCard->name_on_card ?? $gvpCard->card_holder ?? $gvpCard->card_name ?? '';
                if ($gvpHolder === '' && auth()->check()) {
                    $gvpHolder = auth()->user()->fullname ?? auth()->user()->username ?? '';
                }
                $gvpMonth = $gvpCard->expiry_month ?? $gvpCard->exp_month ?? null;
                $gvpYear  = $gvpCard->expiry_year ?? $gvpCard->exp_year ?? null;
                $gvpExp   = ($gvpMonth && $gvpYear) ? (substr('0' . $gvpMonth, -2) . '/' . substr((string) $gvpYear, -2)) : '••/••';
                $gvpBal   = $gvpCard->amount ?? $gvpCard->balance ?? 0;
                $gvpCur   = $gvpCard->currency ?? $gvpCurrency;
                $gvpBrand = $gvpCard->brand ?? $gvpCard->card_brand ?? 'VISA';
            @endphp
            <article class="gvps-card">
                <div class="gvps-card-top">
                    <span class="gvps-chip" aria-hidden="true"></span>
                    <span class="gvps-brand">{{ strtoupper($gvpBrand) }}</span>
                </div>
                <p class="gvps-pan">•••• •••• •••• {{ $gvpLast4 }}</p>
                <div class="gvps-card-foot">
                    <div>
                        <span class="gvps-lab">{{ __('Titulaire') }}</span>
                        <span class="gvps-val">{{ \Illuminate\Support\Str::limit($gvpHolder, 18) }}</span>
                    </div>
                    <div>
                        <span class="gvps-lab">{{ __('Expire') }}</span>
                        <span class="gvps-val">{{ $gvpExp }}</span>
                    </div>
                    <div class="gvps-bal">
                        <span class="gvps-lab">{{ __('Solde') }}</span>
                        <span class="gvps-val">{{ number_format((float) $gvpBal, 2) }} {{ $gvpCur }}</span>
                    </div>
                </div>
            </article>
        @empty
            <article class="gvps-card gvps-card--empty">
                <span class="gvps-empty-plus" aria-hidden="true">+</span>
                <p class="gvps-empty-txt">{{ __("Vous n'avez pas encore de carte") }}</p>
                @if ($gvpUrlCreate)
                    <a href="{{ $gvpUrlCreate }}" class="gvps-empty-cta">{{ __('Créer ma carte') }}</a>
                @endif
            </article>
        @endforelse
    </div>

    @if ($gvpCardCount > 0)
        <p class="gvps-sum">
            {{ $gvpCardCount }} {{ trans_choice('carte active|cartes actives', $gvpCardCount) }}
            · {{ __('Solde total') }} <strong>{{ number_format((float) $gvpCardBalance, 2) }} {{ $gvpCurrency }}</strong>
        </p>
    @endif

    {{-- ── ACTIONS ─────────────────────────────────────────────────────── --}}
    <div class="gvps-acts">

        @if ($gvpUrlFund)
            <a href="{{ $gvpUrlFund }}" class="gvps-act gvps-act--primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span>{{ __('Recharger la carte') }}</span>
            </a>
        @elseif ($gvpUrlCreate)
            <a href="{{ $gvpUrlCreate }}" class="gvps-act gvps-act--primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span>{{ __('Recharger la carte') }}</span>
            </a>
        @endif

        @if ($gvpUrlCreate)
            <a href="{{ $gvpUrlCreate }}" class="gvps-act">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                <span>{{ __('Créer une carte') }}</span>
            </a>
        @endif

        @if ($gvpUrlTopup)
            <a href="{{ $gvpUrlTopup }}" class="gvps-act">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
                <span>{{ __('Recharge mobile') }}</span>
            </a>
        @endif

        @if ($gvpUrlPaylink)
            <a href="{{ $gvpUrlPaylink }}" class="gvps-act">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
                <span>{{ __('PayLink') }}</span>
            </a>
        @endif

    </div>
</div>
