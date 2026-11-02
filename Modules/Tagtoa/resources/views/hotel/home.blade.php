@extends('tagtoa::layouts.dashboard')
@section('title', __('Mon Hôtel'))
@section('page', __('Mon Hôtel'))

@section('content')
@if($hotel)
    {{-- Démarrage pas à pas : tout ce qu'il faut pour ouvrir l'hôtel, dans l'ordre. --}}
    <div class="h-row">
        <h2 style="flex:1"><i class="fa-solid fa-hotel"></i> {{ $hotel->name }}</h2>
        <span class="pill {{ $done === $total ? 'g' : 'n' }}">{{ $done }} / {{ $total }}</span>
    </div>
    <div style="height:8px;background:var(--bd,#eee);border-radius:99px;overflow:hidden;margin:6px 0 14px">
        <div style="height:100%;width:{{ $total ? round($done / $total * 100) : 0 }}%;background:var(--green,#2cb809)"></div>
    </div>
    <p style="color:var(--muted);font-size:13px">{{ __('Suivez les étapes dans l\'ordre : à la fin, vos clients voient vos chambres, vos prix et votre carte, et commandent depuis leur chambre.') }}</p>

    @php $n = 0; @endphp
    @foreach($steps as $s)
        @php if (! ($s['optional'] ?? false)) { $n++; } @endphp
        <a href="{{ $s['url'] }}" class="card" style="display:flex;gap:12px;align-items:center;text-decoration:none;color:inherit;margin-top:10px">
            <span style="flex:0 0 38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;{{ $s['done'] ? 'background:var(--green,#2cb809);color:#fff' : 'background:var(--bd,#eee);color:var(--muted)' }}">
                @if($s['done'])<i class="fa-solid fa-check"></i>@elseif($s['optional'] ?? false)<i class="fa-solid fa-plus"></i>@else{{ $n }}@endif
            </span>
            <span style="flex:1;min-width:0">
                <b><i class="fa-solid {{ $s['icon'] }}" style="color:var(--muted)"></i> {{ $s['title'] }}@if($s['optional'] ?? false) <span class="pill n">{{ __('Optionnel') }}</span>@endif</b>
                <span style="display:block;color:var(--muted);font-size:13px;margin-top:2px">{{ $s['hint'] }}</span>
                <span style="display:block;font-size:12.5px;margin-top:3px;font-weight:600;{{ $s['done'] ? 'color:var(--green,#2cb809)' : '' }}">{{ $s['detail'] }}</span>
            </span>
            <i class="fa-solid fa-chevron-right" style="color:var(--muted)"></i>
        </a>
    @endforeach
@else
    {{-- Le commerce courant n'est pas un hôtel : ouvrir celui du compte, ou en créer un. --}}
    <div class="h-row"><h2 style="flex:1"><i class="fa-solid fa-hotel"></i> {{ __('Mon Hôtel') }}</h2></div>
    <p style="color:var(--muted);font-size:13px">{{ __('Un hôtel a son propre espace : chambres, tarifs par nuit ou par moment, restaurant et bar sur un seul menu, services et demandes des clients.') }}</p>

    @foreach($hotels as $h)
        <form method="POST" action="{{ route('tagtoa.business.switch', $h->id) }}" class="card" style="display:flex;gap:12px;align-items:center;margin-top:10px">
            @csrf
            <input type="hidden" name="to" value="hotel">
            <span style="flex:1;min-width:0"><b><i class="fa-solid fa-hotel" style="color:var(--muted)"></i> {{ $h->name }}</b></span>
            <button class="btn btn-p"><i class="fa-solid fa-door-open"></i> {{ __('Ouvrir mon hôtel') }}</button>
        </form>
    @endforeach

    <div class="card" style="margin-top:10px">
        <h3 style="margin:0 0 6px">{{ $hotels->isEmpty() ? __('Créer mon hôtel') : __('Créer un autre hôtel') }}</h3>
        <p style="color:var(--muted);font-size:13px;margin:0 0 10px">{{ __('Vous choisissez « Hôtel » comme métier ; tout le reste vous est ensuite proposé pas à pas.') }}</p>
        <a class="btn btn-p" href="{{ route('tagtoa.business.create', ['type' => 'hotel']) }}"><i class="fa-solid fa-plus"></i> {{ __('Créer mon hôtel') }}</a>
    </div>
@endif
@endsection
