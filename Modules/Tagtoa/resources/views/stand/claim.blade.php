@extends('tagtoa::layouts.dashboard')
@section('title', __('Activer un stand'))
@section('page', __('Activer votre Smart Stand'))

@section('content')
<div class="card" style="border-left:4px solid #2cb809;max-width:620px">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-circle-check" style="color:#2cb809"></i>
        {{ __('Code accepté') }} — {{ $standId }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px">
        {{ __('Ce stand est réservé pour vous pendant quinze minutes. Donnez-lui un nom d\'emplacement et il sera à vous.') }}
    </p>
</div>

<div class="card" style="max-width:620px">
    <form method="POST" action="{{ route('tagtoa.stand.claim', $standId) }}">
        @csrf
        <label class="lbl">{{ __('Où va ce stand ?') }}</label>
        <input class="inp" name="location_label" maxlength="60" autofocus
               placeholder="{{ __('ex. Table 05, Comptoir, Chambre 12') }}">
        <p style="color:var(--muted);font-size:12.5px;margin-top:6px">
            {{ __('Facultatif — vous pourrez le changer à tout moment, sans rien réimprimer.') }}
        </p>

        @if($errors->any())
            <div style="border-left:3px solid var(--red);background:rgba(192,57,43,.07);
                        padding:11px 13px;border-radius:8px;margin-top:14px">
                @foreach($errors->all() as $e)
                    <div style="color:var(--red);font-size:13.5px">{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <button class="btn btn-p" style="margin-top:18px">
            <i class="fa-solid fa-check"></i> {{ __('Ce stand est à moi') }}
        </button>
    </form>
</div>
@endsection
