@extends('tagtoa::layouts.dashboard')
@section('title', __('Chambres'))
@section('page', __('Chambres').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-bed"></i> {{ __('Chambres') }}
        <span class="pill n">{{ $rooms->count() }}@if($expected) / {{ $expected }}@endif</span>
    </h2>
</div>
@if($expected && $rooms->count() < $expected)
    <p style="color:var(--muted);font-size:13px">{{ __('Votre profil indique :n chambres : il en reste :r à ajouter.', ['n' => $expected, 'r' => $expected - $rooms->count()]) }}</p>
@endif

@if($errors->any())
    <div class="card" style="border-left:4px solid var(--red)">
        @foreach($errors->all() as $e)<div style="color:var(--red);font-size:13.5px">{{ $e }}</div>@endforeach
    </div>
@endif

<div class="card">
    <h3 style="margin:0 0 8px">{{ __('Ajouter une chambre') }}</h3>
    <form method="POST" action="{{ route('tagtoa.hotel.rooms.store') }}">
        @csrf
        <div class="row">
            <div><input class="inp" name="number" maxlength="20" required placeholder="{{ __('Numéro (ex. 101)') }}" value="{{ old('number') }}"></div>
            <div><input class="inp" name="name" maxlength="80" placeholder="{{ __('Nom (facultatif)') }}" value="{{ old('name') }}"></div>
        </div>
        <div class="row" style="margin-top:8px">
            <div>
                <select class="sel" name="type">
                    <option value="">{{ __('Type de chambre') }}</option>
                    @foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ __($l) }}</option>@endforeach
                </select>
            </div>
            <div><input class="inp" name="floor" maxlength="10" placeholder="{{ __('Étage (facultatif)') }}" value="{{ old('floor') }}"></div>
        </div>
        <button class="btn btn-p" style="margin-top:10px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
    </form>

    <details style="margin-top:14px">
        <summary style="cursor:pointer;font-weight:600">{{ __('Ajouter plusieurs chambres d\'un coup') }}</summary>
        <form method="POST" action="{{ route('tagtoa.hotel.rooms.bulk') }}" style="margin-top:8px">
            @csrf
            <div class="row">
                <div><input class="inp" type="number" name="from" min="0" max="99999" required placeholder="{{ __('De (ex. 101)') }}"></div>
                <div><input class="inp" type="number" name="to" min="0" max="99999" required placeholder="{{ __('À (ex. 120)') }}"></div>
            </div>
            <select class="sel" name="type" style="margin-top:8px">
                <option value="">{{ __('Type de chambre') }}</option>
                @foreach($types as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
            </select>
            <p style="color:var(--muted);font-size:12.5px;margin:6px 0 0">{{ __('Au plus :max à la fois. Les numéros déjà existants sont ignorés.', ['max' => $bulkMax]) }}</p>
            <button class="btn btn-o" style="margin-top:8px">{{ __('Créer la série') }}</button>
        </form>
    </details>
</div>

@if($rooms->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-bed"></i>{{ __('Aucune chambre pour le moment.') }}</div></div>
@else
    <div class="card" style="padding:0;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead><tr style="color:var(--muted);text-align:left">
                <th style="padding:8px 10px">{{ __('Chambre') }}</th>
                <th style="padding:8px 10px">{{ __('Type') }}</th>
                <th style="padding:8px 10px">{{ __('Étage') }}</th>
                <th style="padding:8px 10px">{{ __('Statut') }}</th>
                <th style="padding:8px 10px"></th>
            </tr></thead>
            <tbody>
            @foreach($rooms as $r)
                <tr style="border-top:1px solid var(--bd,#eee)">
                    <td style="padding:7px 10px"><b>{{ $r->number }}</b>@if($r->name) <span style="color:var(--muted)">— {{ $r->name }}</span>@endif</td>
                    <td style="padding:7px 10px">{{ $r->type_label ? __($r->type_label) : '—' }}</td>
                    <td style="padding:7px 10px">{{ $r->floor ?: '—' }}</td>
                    <td style="padding:7px 10px"><span class="pill {{ $r->is_active ? 'g' : 'n' }}">{{ $r->is_active ? __('Active') : __('Désactivée') }}</span></td>
                    <td style="padding:7px 10px;white-space:nowrap">
                        <details style="display:inline-block">
                            <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex"><i class="fa-solid fa-pen"></i></summary>
                            <form method="POST" action="{{ route('tagtoa.hotel.rooms.update', $r->id) }}" style="margin-top:8px;min-width:240px">
                                @csrf @method('PUT')
                                <input class="inp" name="number" maxlength="20" required value="{{ $r->number }}">
                                <input class="inp" name="name" maxlength="80" value="{{ $r->name }}" placeholder="{{ __('Nom') }}" style="margin-top:6px">
                                <select class="sel" name="type" style="margin-top:6px">
                                    <option value="">{{ __('Type de chambre') }}</option>
                                    @foreach($types as $k => $l)<option value="{{ $k }}" @selected($r->type === $k)>{{ __($l) }}</option>@endforeach
                                </select>
                                <input class="inp" name="floor" maxlength="10" value="{{ $r->floor }}" placeholder="{{ __('Étage') }}" style="margin-top:6px">
                                <input class="inp" name="notes" maxlength="300" value="{{ $r->notes }}" placeholder="{{ __('Notes internes') }}" style="margin-top:6px">
                                <button class="btn btn-p btn-sm" style="margin-top:6px">{{ __('Enregistrer') }}</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('tagtoa.hotel.rooms.toggle', $r->id) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-o btn-sm" title="{{ $r->is_active ? __('Désactiver') : __('Activer') }}">
                                <i class="fa-solid {{ $r->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
