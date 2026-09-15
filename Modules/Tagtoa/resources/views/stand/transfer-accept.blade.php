@extends('tagtoa::layouts.dashboard')
@section('title', __('Reprendre des stands'))
@section('page', __('Reprendre des stands'))

@push('head')
<style>
.ic{width:100%;padding:12px 13px;border:1.5px solid var(--bd);border-radius:10px;
    font:700 19px/1.4 monospace;letter-spacing:.1em;text-transform:uppercase;background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.lb{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}
.dest{display:flex;gap:10px;align-items:center;background:var(--blue-pale);border-radius:11px;padding:12px 14px}
.dest i{color:var(--blue-deep);font-size:17px}
</style>
@endpush

@section('content')

<form method="POST" action="{{ route('tagtoa.stand.transfer.accept') }}" class="card" style="max-width:520px">
    @csrf

    <div class="h-row" style="margin-bottom:4px"><h2>{{ __('Saisissez le code reçu') }}</h2></div>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:14px;max-width:60ch">
        {{ __('Le code que l\'ancien propriétaire vous a transmis. Il ne sert qu\'une fois.') }}
    </p>

    {{-- NOMMER LE COMMERCE QUI RECEVRA.
         Un compte TAGTOA peut tenir plusieurs commerces. Reprendre quarante
         stands dans la boulangerie au lieu du bar est une erreur silencieuse :
         rien n'échoue, et on ne s'en aperçoit qu'en cherchant les stands là où
         ils ne sont pas. --}}
    <div class="dest" style="margin-bottom:14px">
        <i class="fa-solid fa-shop"></i>
        <span style="font-size:13.5px;min-width:0">
            {{ __('Ces stands rejoindront') }}
            <b style="display:block;font-family:var(--fh,sans-serif)">{{ $nomCommerce ?: __('votre commerce') }}</b>
            <span style="color:var(--muted);font-size:12px">{{ __('Changez de commerce avant de valider si ce n\'est pas le bon.') }}</span>
        </span>
    </div>

    <label class="lb" for="code">{{ __('Code de cession') }}</label>
    <input class="ic" id="code" name="code" required maxlength="40" autocomplete="off"
           autocapitalize="characters" spellcheck="false"
           placeholder="XXXX-XXXX-XXXX" autofocus>
    <p style="color:var(--muted);font-size:11.5px;margin-top:6px">
        {{ __('Les tirets sont facultatifs, et les majuscules aussi.') }}
    </p>

    <button class="btn btn-p" style="margin-top:16px">
        <i class="fa-solid fa-check"></i> {{ __('Reprendre les stands') }}
    </button>
    <a class="btn btn-o" href="{{ route('tagtoa.stand.transfer.index') }}" style="margin-top:8px">
        {{ __('Retour') }}
    </a>
</form>

@endsection
