@extends('tagtoa::layouts.dashboard')
@section('title', __('Rapport d\'entrée'))
@section('page', __('Rapport d\'entrée') . ' — ' . $event->title)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Entrées en temps réel') }}</h2>
    <span class="pill g" id="live"><i class="fa-solid fa-circle" style="font-size:8px"></i> {{ __('En direct') }}</span>
</div>

<div class="grid g3">
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-right-to-bracket"></i></div><div class="v" id="s-in">—</div><div class="k">{{ __('Entrés') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-ticket"></i></div><div class="v" id="s-tickets">—</div><div class="k">{{ __('Billets') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-percent"></i></div><div class="v" id="s-pct">—</div><div class="k">{{ __('Taux d\'entrée') }}</div></div>
</div>

{{-- Entrées par jour (événement multi-jour : Jour 1 / Jour 2…). Masqué si 1 seul jour. --}}
<div class="grid g3" id="byDay" style="margin-top:12px;display:none"></div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Dernières entrées') }}</h2></div>
    <table>
        <thead><tr><th>{{ __('Participant') }}</th><th>{{ __('Heure') }}</th><th>{{ __('Méthode') }}</th><th>{{ __('Porte') }}</th></tr></thead>
        <tbody id="rows"><tr><td colspan="4" class="empty" style="padding:24px">{{ __('En attente…') }}</td></tr></tbody>
    </table>
</div>

@push('scripts')
<script>
var STATS_URL="{{ route('tagtoa.event.dashboard.checkin.stats', $event->id) }}";
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
function load(){
    fetch(STATS_URL,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
        document.getElementById('s-in').textContent=d.checked_in;
        document.getElementById('s-tickets').textContent=d.tickets;
        document.getElementById('s-pct').textContent=d.percent+'%';
        var bd=(d.by_day||[]),bdEl=document.getElementById('byDay');
        if(bd.length>1){bdEl.style.display='';bdEl.innerHTML=bd.map(function(x){return '<div class="stat"><div class="ic" style="background:#eef2ff;color:#3344aa"><i class="fa-solid fa-calendar-day"></i></div><div class="v">'+x.count+'</div><div class="k">'+esc(x.label)+'</div></div>';}).join('');}else{bdEl.style.display='none';}
        var rows=(d.recent||[]);
        var tb=document.getElementById('rows');
        tb.innerHTML=rows.length?rows.map(function(x){return '<tr><td><b style="font-family:var(--fh)">'+esc(x.name)+'</b></td><td style="color:var(--muted)">'+esc(x.time)+'</td><td>'+esc(x.method||'')+'</td><td>'+esc(x.gate||'—')+'</td></tr>';}).join(''):'<tr><td colspan="4" class="empty" style="padding:24px">{{ __('Aucune entrée pour le moment.') }}</td></tr>';
    }).catch(function(){});
}
load(); setInterval(load, 5000);
</script>
@endpush
@endsection
