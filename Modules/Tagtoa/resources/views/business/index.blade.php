@extends('tagtoa::layouts.dashboard')
@section('title', __('Mes commerces'))
@section('page', __('Mes commerces'))

@section('content')
<div class="h-row" style="margin-bottom:14px">
    <p style="color:var(--muted);font-size:13.5px;margin:0;max-width:62ch">
        {{ __('Chaque commerce a son propre menu, sa caisse, ses employés et ses ventes. Rien ne se mélange entre eux.') }}
    </p>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.business.create') }}" class="btn btn-p btn-sm">
        <i class="fa-solid fa-plus"></i> {{ __('Nouveau commerce') }}
    </a>
</div>

@if($businesses->isEmpty())
    <div class="card" style="text-align:center;padding:38px">
        <i class="fa-solid fa-shop" style="font-size:32px;color:var(--muted);opacity:.5"></i>
        <p style="color:var(--muted);margin-top:12px;max-width:46ch;margin-left:auto;margin-right:auto">
            {{ __('Vous n\'avez pas encore déclaré votre commerce. En attendant, tout continue de fonctionner — mais TAGTOA ne peut pas encore s\'adapter à votre métier.') }}
        </p>
        <a href="{{ route('tagtoa.business.create') }}" class="btn btn-p" style="margin-top:14px">
            <i class="fa-solid fa-check"></i> {{ __('Déclarer mon commerce') }}
        </a>
    </div>
@else
    <div class="grid g2">
        @foreach($businesses as $b)
            @php $actif = $b->id === $courant; @endphp
            <div class="card" style="{{ $actif ? 'border-left:4px solid #2cb809' : '' }}{{ $b->is_active ? '' : ';opacity:.6' }}">
                <div style="display:flex;align-items:center;gap:13px">
                    @if($b->logo_url)
                        <img src="{{ $b->logo_url }}" alt="" style="width:46px;height:46px;border-radius:12px;object-fit:cover;flex:0 0 46px">
                    @else
                        <span class="bini">{{ $b->initials }}</span>
                    @endif
                    <div style="flex:1;min-width:0">
                        <b style="font-family:var(--fh);font-size:16px;display:block">{{ $b->name }}</b>
                        <div style="color:var(--muted);font-size:13px;margin-top:2px">
                            {{ __($b->type_label) }} · {{ $b->sells_label }} · {{ $b->currency }}
                        </div>
                    </div>
                    @if($actif)<span class="pill g" style="font-size:11px">{{ __('En cours') }}</span>@endif
                </div>

                @if($b->address)
                    <div style="color:var(--muted);font-size:13px;margin-top:10px">
                        <i class="fa-solid fa-location-dot"></i> {{ $b->address }}
                    </div>
                @endif

                @if($b->categories)
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:11px">
                        @foreach(array_slice($b->categories, 0, 6) as $c)
                            <span class="cchip">{{ $c }}</span>
                        @endforeach
                        @if(count($b->categories) > 6)
                            <span class="cchip">+{{ count($b->categories) - 6 }}</span>
                        @endif
                    </div>
                @endif

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                    @unless($actif)
                        <form method="POST" action="{{ route('tagtoa.business.switch',$b->id) }}">@csrf
                            <button class="btn btn-p btn-sm"><i class="fa-solid fa-arrow-right-arrow-left"></i> {{ __('Travailler dessus') }}</button>
                        </form>
                    @endunless
                    <a href="{{ route('tagtoa.business.edit',$b->id) }}" class="btn btn-o btn-sm">
                        <i class="fa-solid fa-pen"></i> {{ __('Modifier') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif

<style>
    .bini{width:46px;height:46px;border-radius:12px;background:var(--blue);color:#fff;flex:0 0 46px;
          display:inline-flex;align-items:center;justify-content:center;font:700 16px var(--fh,sans-serif)}
    .cchip{font-size:11.5px;background:var(--blue-pale);color:var(--blue-deep);
           border-radius:999px;padding:3px 10px;font-weight:600}
</style>
@endsection
