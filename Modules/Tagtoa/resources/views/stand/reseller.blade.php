@extends('tagtoa::layouts.dashboard')
@section('title', __('Revendeur'))
@section('page', __('Mon stock TAGTOA'))

@push('head')
<style>
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.filtres{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}
.filtres::-webkit-scrollbar{display:none}
.filtres a{flex:0 0 auto;padding:7px 13px;border-radius:999px;border:1.5px solid var(--bd);
           background:#fff;font:600 13px var(--fh);color:#4a4a4a;white-space:nowrap}
.filtres a.on{background:var(--blk);border-color:var(--blk);color:#fff}
.st{display:flex;gap:10px;align-items:center;padding:10px 0}
.st + .st{border-top:1px solid var(--bd)}
.st .id{font:700 14px var(--fh);font-family:monospace;flex:1;min-width:0}
</style>
@endpush

@section('content')
<div class="card" style="border-left:4px solid var(--amber)">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-lock" style="color:var(--amber)"></i> {{ __('Les codes d\'activation ne sont pas ici — et c\'est voulu') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:74ch">
        {{-- Le dire à l'écran, plutôt que de laisser le revendeur chercher :
             ce n'est pas une fonctionnalité manquante, c'est la garantie qu'il
             vend au commerçant. --}}
        {{ __('Chaque stand porte son code sous un panneau à gratter. C\'est le commerçant qui le gratte et qui active son stand — vous n\'avez jamais à le voir, et personne ne peut activer un stand à sa place.') }}
    </p>
</div>

<div class="grid g3" style="margin-bottom:16px">
    <div class="stat">
        <div class="ic" style="width:42px;height:42px;border-radius:11px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="v">{{ $compte['en_stock'] }}</div><div class="k">{{ __('En stock chez vous') }}</div>
    </div>
    <div class="stat">
        <div class="ic" style="width:42px;height:42px;border-radius:11px;background:#eafaf3;color:#0e5f44;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px"><i class="fa-solid fa-handshake"></i></div>
        <div class="v">{{ $compte['vendus'] }}</div><div class="k">{{ __('Vendus') }}</div>
    </div>
    <div class="stat">
        <div class="ic" style="width:42px;height:42px;border-radius:11px;background:#fff5e6;color:#7a5200;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px"><i class="fa-solid fa-percent"></i></div>
        <div class="v">{{ rtrim(rtrim(number_format($reseller->commission_pct, 2, '.', ''), '0'), '.') }}%</div>
        <div class="k">{{ __('Votre commission') }}</div>
    </div>
</div>

<form method="POST" action="{{ route('tagtoa.reseller.sell') }}" class="card">
    @csrf
    <div class="h-row" style="margin-bottom:4px"><h2>{{ __('Déclarer une vente') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:10px">
        {{ __('Le numéro imprimé au recto du stand. Le commerçant l\'activera lui-même ensuite.') }}
    </p>
    <div class="pf">
        <div>
            <label for="pid">{{ __('Numéro du stand') }}</label>
            <input class="ic" id="pid" name="public_id" required maxlength="24"
                   placeholder="TG-000041" style="font-family:monospace">
        </div>
        <div>
            <label for="bn">{{ __('Acheteur') }}</label>
            <input class="ic" id="bn" name="buyer_name" maxlength="120" placeholder="{{ __('Facultatif') }}">
        </div>
        <div>
            <label for="bp">{{ __('Téléphone') }}</label>
            <input class="ic" id="bp" name="buyer_phone" maxlength="40" inputmode="tel" placeholder="{{ __('Facultatif') }}">
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-p" style="width:100%"><i class="fa-solid fa-check"></i> {{ __('Vendu') }}</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="h-row"><h2>{{ __('Vos stands') }}</h2></div>

    @php $e = $filtre['etat'] ?? 'stock'; @endphp
    <div class="filtres" style="margin-bottom:10px">
        <a href="{{ request()->fullUrlWithQuery(['etat' => 'stock', 'page' => null]) }}" class="{{ $e === 'stock' ? 'on' : '' }}">{{ __('En stock') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['etat' => 'vendus', 'page' => null]) }}" class="{{ $e === 'vendus' ? 'on' : '' }}">{{ __('Vendus') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['etat' => 'tous', 'page' => null]) }}" class="{{ $e === 'tous' ? 'on' : '' }}">{{ __('Tous') }}</a>
    </div>

    <form method="GET" style="display:flex;gap:8px;margin-bottom:10px">
        <input type="hidden" name="etat" value="{{ $e }}">
        <input class="ic" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="{{ __('TG-000041') }}" style="flex:1">
        <button class="btn btn-d btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    @forelse($stands as $s)
        <div class="st">
            <span class="id">{{ $s->public_id }}</span>
            <span class="pill {{ $s->physical_state === 'sold' ? 'g' : 'n' }}">
                {{ __($s->physical_label) }}
            </span>
            {{-- L'état NUMÉRIQUE est visible, mais en lecture seule : il dit au
                 revendeur si son client a bien activé son stand — ce qui est
                 exactement la question qu'un client rappelle pour poser. --}}
            <span class="pill {{ $s->digital_state === 'active' ? 'g' : 'n' }}">
                {{ __($s->digital_label) }}
            </span>
            <a class="btn btn-o btn-sm" href="{{ route('tagtoa.reseller.history', $s->id) }}">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </a>
        </div>
    @empty
        <div class="empty" style="padding:30px 16px">
            <i class="fa-solid fa-boxes-stacked"></i>
            {{ __('Rien ici. TAGTOA vous affecte des cartons depuis sa console.') }}
        </div>
    @endforelse

    @if($stands->hasPages())<div style="margin-top:14px">{{ $stands->links() }}</div>@endif
</div>
@endsection
