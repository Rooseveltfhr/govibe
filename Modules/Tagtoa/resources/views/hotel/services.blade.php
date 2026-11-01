@extends('tagtoa::layouts.dashboard')
@section('title', __('Services'))
@section('page', __('Services').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-bell-concierge"></i> {{ __('Services') }}
        <span class="pill n">{{ $services->count() }}</span>
    </h2>
    <form method="POST" action="{{ route('tagtoa.hotel.services.defaults') }}">
        @csrf
        <button class="btn btn-o btn-sm"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Ajouter les services standards') }}</button>
    </form>
</div>
<p style="color:var(--muted);font-size:13px">{{ __('Ce que vos clients pourront demander depuis le QR de leur chambre. Les prix sont affichés à titre indicatif : aucun paiement en ligne.') }}</p>

@if($errors->any())
    <div class="card" style="border-left:4px solid var(--red)">
        @foreach($errors->all() as $e)<div style="color:var(--red);font-size:13.5px">{{ $e }}</div>@endforeach
    </div>
@endif

<div class="card">
    <h3 style="margin:0 0 8px">{{ __('Ajouter un service') }}</h3>
    <form method="POST" action="{{ route('tagtoa.hotel.services.store') }}">
        @csrf
        @include('tagtoa::hotel._service-fields', ['s' => null])
        <button class="btn btn-p" style="margin-top:10px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
    </form>
</div>

@if($services->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-bell-concierge"></i>{{ __('Aucun service pour le moment.') }}</div></div>
@else
    <div class="card" style="padding:0;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead><tr style="color:var(--muted);text-align:left">
                <th style="padding:8px 10px">{{ __('Service') }}</th>
                <th style="padding:8px 10px">{{ __('Nature') }}</th>
                <th style="padding:8px 10px">{{ __('Équipe') }}</th>
                <th style="padding:8px 10px">{{ __('Prix') }}</th>
                <th style="padding:8px 10px">{{ __('Statut') }}</th>
                <th style="padding:8px 10px"></th>
            </tr></thead>
            <tbody>
            @foreach($services as $s)
                <tr style="border-top:1px solid var(--bd,#eee)">
                    <td style="padding:7px 10px"><i class="fa-solid {{ $s->icon }}" style="color:var(--muted)"></i> <b>{{ $s->name }}</b>
                        @if($s->kind === 'room_service' && ($m = $menus->firstWhere('id', $s->menu_id)))<div style="color:var(--muted);font-size:12px">{{ __('Menu') }} : {{ $m->name }}</div>@endif
                    </td>
                    <td style="padding:7px 10px">{{ __($s->kind_label) }}</td>
                    <td style="padding:7px 10px">{{ $s->team ? __($teams[$s->team] ?? $s->team) : '—' }}</td>
                    <td style="padding:7px 10px">{{ $s->price !== null ? number_format((float) $s->price, 2).' '.$hotel->currency : __('Gratuit') }}</td>
                    <td style="padding:7px 10px"><span class="pill {{ $s->is_active ? 'g' : 'n' }}">{{ $s->is_active ? __('Actif') : __('Désactivé') }}</span></td>
                    <td style="padding:7px 10px;white-space:nowrap">
                        <details style="display:inline-block">
                            <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex"><i class="fa-solid fa-pen"></i></summary>
                            <form method="POST" action="{{ route('tagtoa.hotel.services.update', $s->id) }}" style="margin-top:8px;min-width:260px">
                                @csrf @method('PUT')
                                @include('tagtoa::hotel._service-fields', ['s' => $s])
                                <button class="btn btn-p btn-sm" style="margin-top:6px">{{ __('Enregistrer') }}</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('tagtoa.hotel.services.toggle', $s->id) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-o btn-sm" title="{{ $s->is_active ? __('Désactiver') : __('Activer') }}">
                                <i class="fa-solid {{ $s->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
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
