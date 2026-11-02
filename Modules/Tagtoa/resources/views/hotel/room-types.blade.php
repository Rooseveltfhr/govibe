@extends('tagtoa::layouts.dashboard')
@section('title', __('Tarifs des chambres'))
@section('page', __('Tarifs des chambres').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-tags"></i> {{ __('Tarifs des chambres') }}
        <span class="pill n">{{ $types->count() }}</span>
    </h2>
</div>
<p style="color:var(--muted);font-size:13px">{{ __('Ce que vos clients voient sur la page de l\'hôtel : chaque type de chambre, son prix par nuit ou par moment (1 h, 2 h…), et ce qui est inclus. Aucun paiement en ligne : le prix est affiché à titre d\'information.') }}</p>

@if($errors->any())
    <div class="card" style="border-left:4px solid var(--red)">
        @foreach($errors->all() as $e)<div style="color:var(--red);font-size:13.5px">{{ $e }}</div>@endforeach
    </div>
@endif

<div class="card">
    <h3 style="margin:0 0 8px">{{ __('Ajouter un type de chambre') }}</h3>
    <form method="POST" action="{{ route('tagtoa.hotel.roomtypes.store') }}">
        @csrf
        <div class="duo">
            <input name="capacity" class="inp" type="number" min="1" max="50" placeholder="{{ __('Personnes') }}" value="{{ old('capacity') }}">
            <input name="name" class="inp" required maxlength="80" placeholder="{{ __('Ex. Chambre climatisée, Suite…') }}" value="{{ old('name') }}">
        </div>
        <input name="description" class="inp" maxlength="300" style="margin-top:8px" placeholder="{{ __('Description (optionnel)') }}" value="{{ old('description') }}">
        <button class="btn btn-p" style="margin-top:10px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
    </form>
</div>

@forelse($types as $t)
    <div class="card" @if(! $t->is_active) style="opacity:.65" @endif>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <h3 style="margin:0;flex:1"><i class="fa-solid fa-bed" style="color:var(--muted)"></i> {{ $t->name }}
                @if($t->capacity)<small style="color:var(--muted);font-weight:500"> · {{ $t->capacity }} <i class="fa-solid fa-user"></i></small>@endif
                <span class="pill {{ $t->is_active ? 'g' : 'n' }}">{{ $t->is_active ? __('Actif') : __('Désactivé') }}</span>
            </h3>
            <details style="display:inline-block">
                <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex"><i class="fa-solid fa-pen"></i></summary>
                <form method="POST" action="{{ route('tagtoa.hotel.roomtypes.update', $t->id) }}" style="margin-top:8px;min-width:260px">
                    @csrf @method('PUT')
                    <div class="duo">
                        <input name="capacity" class="inp" type="number" min="1" max="50" value="{{ $t->capacity }}" placeholder="{{ __('Personnes') }}">
                        <input name="name" class="inp" required maxlength="80" value="{{ $t->name }}">
                    </div>
                    <input name="description" class="inp" maxlength="300" style="margin-top:8px" value="{{ $t->description }}" placeholder="{{ __('Description (optionnel)') }}">
                    <button class="btn btn-p btn-sm" style="margin-top:6px">{{ __('Enregistrer') }}</button>
                </form>
            </details>
            <form method="POST" action="{{ route('tagtoa.hotel.roomtypes.toggle', $t->id) }}" style="display:inline">
                @csrf
                <button class="btn btn-o btn-sm" title="{{ $t->is_active ? __('Désactiver') : __('Activer') }}"><i class="fa-solid {{ $t->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
            </form>
        </div>
        @if($t->description)<div style="color:var(--muted);font-size:13px;margin-top:4px">{{ $t->description }}</div>@endif

        <div style="margin-top:10px">
            @forelse($t->plans as $p)
                <div style="border-top:1px solid var(--bd,#eee);padding:8px 0;display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;@if(! $p->is_active) opacity:.6 @endif">
                    <div style="flex:1;min-width:200px">
                        <b>{{ $p->duration_label }}</b> — {{ \Modules\Tagtoa\App\Support\Money::format((float) $p->price, $p->currency) }}
                        @if(! $p->is_active)<span class="pill n">{{ __('Désactivé') }}</span>@endif
                        <div style="font-size:12.5px;color:var(--muted);margin-top:3px">
                            @forelse($p->inclusionItems() as $inc)<span style="margin-right:10px"><i class="fa-solid fa-check" style="color:var(--green,#2cb809)"></i> {{ $inc['label'] }}</span>@empty{{ __('Rien d\'inclus indiqué') }}@endforelse
                        </div>
                    </div>
                    <details>
                        <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex"><i class="fa-solid fa-pen"></i></summary>
                        <form method="POST" action="{{ route('tagtoa.hotel.rateplans.update', $p->id) }}" style="margin-top:8px;min-width:280px">
                            @csrf @method('PUT')
                            @include('tagtoa::hotel._plan-fields', ['p' => $p, 't' => $t])
                            <button class="btn btn-p btn-sm" style="margin-top:8px">{{ __('Enregistrer') }}</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('tagtoa.hotel.rateplans.toggle', $p->id) }}" style="display:inline">
                        @csrf
                        <button class="btn btn-o btn-sm" title="{{ $p->is_active ? __('Désactiver') : __('Activer') }}"><i class="fa-solid {{ $p->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
                    </form>
                </div>
            @empty
                <div style="color:var(--muted);font-size:13px">{{ __('Aucun tarif pour ce type : ajoutez la nuit et/ou un moment.') }}</div>
            @endforelse
        </div>

        <details style="margin-top:8px">
            <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un tarif') }}</summary>
            <form method="POST" action="{{ route('tagtoa.hotel.rateplans.store', $t->id) }}" style="margin-top:8px;max-width:520px">
                @csrf
                @include('tagtoa::hotel._plan-fields', ['p' => null, 't' => $t])
                <button class="btn btn-p btn-sm" style="margin-top:8px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter ce tarif') }}</button>
            </form>
        </details>
    </div>
@empty
    <div class="card"><div class="empty"><i class="fa-solid fa-bed"></i>{{ __('Aucun type de chambre pour le moment.') }}</div></div>
@endforelse
@endsection
