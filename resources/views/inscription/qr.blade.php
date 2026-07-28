@extends('layouts.app')

@section('title', 'QR Code — {{ $inscription->numero_inscription }}')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-sm w-full text-center">
        <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center gold-gradient">
            <span class="font-black text-xl" style="color:#1e3a5f">G</span>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-1">GOVIBE Academy</h2>
        <p class="text-gray-500 text-sm mb-5">Carte de participant</p>

        @if($inscription->qr_code)
            <img src="data:image/png;base64,{{ $inscription->qr_code }}" alt="QR Code" class="w-48 h-48 mx-auto border-2 border-gray-200 rounded-xl p-2 mb-5">
        @endif

        <div class="bg-gray-50 rounded-xl p-4 text-left space-y-2">
            <p class="text-xs text-gray-500">N° Inscription</p>
            <p class="font-bold text-lg text-blue-800">{{ $inscription->numero_inscription }}</p>
            <hr>
            <p class="text-sm font-medium text-gray-800">{{ $inscription->nom_complet }}</p>
            <p class="text-sm text-gray-500">{{ $inscription->formation->nom ?? '' }}</p>
        </div>
    </div>
</div>
@endsection
