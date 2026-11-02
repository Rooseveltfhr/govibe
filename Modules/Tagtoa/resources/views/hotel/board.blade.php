@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran du personnel'))
@section('page', __('Écran du personnel').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-bell-concierge"></i> {{ __('Demandes à traiter') }}
        <span class="pill n">{{ $requests->count() }}</span></h2>
    <span style="color:var(--muted);font-size:13px">{{ $me->name }} · {{ __($me->role === 'hotel_staff' ? ($me->hotel_team ? \Modules\Tagtoa\App\Support\Hotel\HotelServiceRules::STAFF_TEAMS[$me->hotel_team] ?? '' : '') : $me->role_label) }}</span>
    <form method="POST" action="{{ route('tagtoa.hotel.board.logout') }}" style="display:inline">@csrf<button class="btn btn-o btn-sm">{{ __('Changer de personne') }}</button></form>
</div>

@if($requests->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-circle-check"></i>{{ __('Rien à traiter pour le moment.') }}</div></div>
@else
    @foreach($requests as $r)
        <div class="card" style="margin-bottom:10px">
            <div style="display:flex;gap:10px;align-items:baseline">
                <b style="font-size:22px">{{ optional($rooms->get($r->room_id))->number ?? '—' }}</b>
                <span style="flex:1"><b>{{ $r->service_name }}</b></span>
                <span class="pill {{ $r->status === 'new' ? 'g' : 'n' }}">{{ __($statuses[$r->status] ?? $r->status) }}</span>
            </div>
            @if($r->note)<div style="color:var(--muted);font-size:13.5px;white-space:pre-line;margin-top:4px">{{ $r->note }}</div>@endif
            <div style="color:var(--muted);font-size:12px;margin-top:4px">{{ $r->reference }} · {{ $r->created_at?->format('d/m H:i') }}</div>
            <div style="margin-top:8px">
                @foreach(($next[$r->status] ?? []) as $to)
                    @if($to === 'rejected')
                        <details style="display:inline-block">
                            <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex">{{ __($statuses[$to]) }}</summary>
                            <form method="POST" action="{{ route('tagtoa.hotel.board.status', $r->id) }}" style="margin-top:6px">
                                @csrf <input type="hidden" name="status" value="rejected">
                                <input class="inp" name="reason" maxlength="200" placeholder="{{ __('Motif (visible par l\'invité)') }}">
                                <button class="btn btn-o btn-sm" style="margin-top:6px">{{ __('Confirmer') }}</button>
                            </form>
                        </details>
                    @else
                        <form method="POST" action="{{ route('tagtoa.hotel.board.status', $r->id) }}" style="display:inline">
                            @csrf <input type="hidden" name="status" value="{{ $to }}">
                            <button class="btn {{ $to === 'done' ? 'btn-p' : 'btn-o' }} btn-sm">{{ __($statuses[$to]) }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
@endif

<script>
(function(){
    var dernier = {{ (int) $requests->max('id') }};
    function verifier(){
        fetch(@json(route('tagtoa.hotel.board.alerts')), {headers:{'Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(j){
                if (!j) return;
                if (j.latest_id > dernier) { if (window.TagtoaSound) { TagtoaSound.alerte(8); } location.reload(); }
            }).catch(function(){});
    }
    setInterval(verifier, 15000);
})();
</script>
@endsection
