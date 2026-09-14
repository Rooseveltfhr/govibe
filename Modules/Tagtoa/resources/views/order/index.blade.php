@extends('tagtoa::layouts.dashboard')
@section('title', __('Commandes'))
@section('page', __('Commandes'))

@push('head')
<style>
[hidden]{display:none!important}
.filtres{display:flex;gap:7px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none}
.filtres::-webkit-scrollbar{display:none}
.filtres a{flex:0 0 auto;padding:7px 13px;border-radius:999px;border:1.5px solid var(--bd);
           background:#fff;font:600 13px var(--fh);color:#4a4a4a;white-space:nowrap}
.filtres a.on{background:var(--blk);border-color:var(--blk);color:#fff}
.cmd{display:flex;gap:11px;align-items:center;padding:13px 0;border-top:1px solid var(--bd)}
.cmd:first-of-type{border-top:0}
.cmd .can{width:38px;height:38px;border-radius:11px;flex:0 0 38px;display:flex;align-items:center;
          justify-content:center;background:var(--blue-pale);color:var(--blue-deep);font-size:15px}
.cmd .corps{flex:1;min-width:0}
.cmd .ref{font:700 14px var(--fh);display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.cmd .qui{color:var(--muted);font-size:13px;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cmd .arg{text-align:right;flex:0 0 auto}
.cmd .arg b{font:700 15px var(--fh);display:block}
.cmd .arg small{color:var(--muted);font-size:11.5px}
</style>
@endpush

@section('content')
{{-- Ce que le marchand veut savoir en ouvrant l'écran, avant toute liste. --}}
<div class="grid g3" style="margin-bottom:16px">
    <div class="stat">
        <div class="ic"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="v">{{ \Modules\Tagtoa\App\Support\Money::format($totaux['total'], $totaux['devise']) }}</div>
        <div class="k">{{ __('Encaissé aujourd\'hui') }}</div>
    </div>
    <div class="stat">
        <div class="ic"><i class="fa-solid fa-receipt"></i></div>
        <div class="v">{{ $totaux['nombre'] }}</div>
        <div class="k">{{ __('Commandes du jour') }}</div>
    </div>
    <div class="stat">
        <div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="v">{{ $totaux['ouvertes'] }}</div>
        <div class="k">{{ __('À servir') }}</div>
    </div>
</div>

<div class="card">
    <form method="GET" style="margin-bottom:12px">
        <div style="display:flex;gap:8px;margin-bottom:10px">
            <input class="inp" name="q" value="{{ $filtres['q'] ?? '' }}" style="flex:1"
                   placeholder="{{ __('Référence, nom ou téléphone…') }}">
            <button class="btn btn-d"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </form>

    {{-- « Ouvertes » d'abord : c'est la question qu'on se pose en service. --}}
    @php $c = $filtres['channel'] ?? null; $s = $filtres['status'] ?? null; @endphp
    <div class="filtres" style="margin-bottom:10px">
        <a href="{{ request()->fullUrlWithQuery(['status' => 'open', 'page' => null]) }}" class="{{ $s === 'open' ? 'on' : '' }}">
            <i class="fa-solid fa-hourglass-half"></i> {{ __('À servir') }}
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'channel' => null, 'page' => null]) }}" class="{{ ! $s && ! $c ? 'on' : '' }}">
            {{ __('Toutes') }}
        </a>
        @foreach($channels as $cle => $label)
            <a href="{{ request()->fullUrlWithQuery(['channel' => $cle, 'page' => null]) }}" class="{{ $c === $cle ? 'on' : '' }}">
                {{ __($label) }}
            </a>
        @endforeach
    </div>

    @forelse($orders as $o)
        <div class="cmd">
            <div class="can" title="{{ __($o->channel_label) }}">
                <i class="fa-solid {{ [
                    'pos'   => 'fa-cash-register',
                    'menu'  => 'fa-utensils',
                    'store' => 'fa-bag-shopping',
                    'event' => 'fa-ticket',
                    'link'  => 'fa-link',
                ][$o->channel] ?? 'fa-receipt' }}"></i>
            </div>
            <div class="corps">
                <div class="ref">
                    {{ $o->reference ?: '#'.$o->id }}
                    <span class="pill {{ in_array($o->status, ['completed'], true) ? 'g' : (in_array($o->status, ['cancelled','refunded'], true) ? 'r' : 'a') }}">
                        {{ __($o->status_label) }}
                    </span>
                    @if($o->payment_status !== 'paid')
                        <span class="pill n">{{ __($o->payment_label) }}</span>
                    @endif
                </div>
                <div class="qui">
                    {{-- Le client anonyme est la NORME, pas un défaut : on ne
                         montre pas « — » comme s'il manquait quelque chose. --}}
                    {{ $o->customer_name ?: __('Client de passage') }}
                    · {{ optional($o->placed_at)->format('d/m H:i') }}
                </div>
            </div>
            <div class="arg">
                <b>{{ \Modules\Tagtoa\App\Support\Money::format($o->total, $o->currency) }}</b>
                <small>{{ __($o->channel_label) }}</small>
            </div>
        </div>
    @empty
        <div class="empty" style="padding:34px 16px">
            <i class="fa-solid fa-receipt"></i>
            {{ __('Aucune commande pour l\'instant.') }}
        </div>
    @endforelse

    @if($orders->hasPages())
        <div style="margin-top:14px">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
