@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran du personnel'))
@section('page', __('Écran du personnel').' — '.$hotel->name)

@section('content')
<div class="card" style="max-width:420px;margin:30px auto;text-align:center">
    <h2 style="margin:0 0 6px"><i class="fa-solid fa-bell-concierge"></i> {{ __('Demandes des chambres') }}</h2>
    <p style="color:var(--muted);font-size:14px">{{ __('Entrez votre code personnel.') }}</p>
    <form method="POST" action="{{ route('tagtoa.hotel.board.login') }}">
        @csrf
        <input class="inp" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required autofocus
               style="text-align:center;letter-spacing:.4em;font-size:22px;max-width:220px;margin:10px auto">
        @error('pin')<div style="color:var(--red);font-size:13.5px">{{ $message }}</div>@enderror
        <button class="btn btn-p" style="margin-top:12px">{{ __('Entrer') }}</button>
    </form>
</div>
@endsection
