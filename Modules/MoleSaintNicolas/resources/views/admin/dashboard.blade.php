@extends('layouts.admin')

@section('title', 'Tableau de bord — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-msn-sea-900">Tableau de bord</h1>
                <p class="text-sm text-msn-sea-700">Connecté en tant que {{ auth()->user()->name }}
                    ({{ auth()->user()->getRoleNames()->implode(', ') }})</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-msn-sea-500 px-4 py-2 text-sm font-semibold text-msn-sea-900 hover:bg-msn-sea-50">
                    Se déconnecter
                </button>
            </form>
        </header>

        <div class="mt-10 rounded-2xl border border-dashed border-msn-sea-500/40 bg-white p-8 text-msn-sea-700">
            <p class="font-semibold text-msn-sea-900">Phase 1 — Fondation</p>
            <p class="mt-2 text-sm">
                Ce dashboard est le socle (auth, rôles, navigation). La gestion de contenu
                (Territoire, Histoire, Sites historiques, Hôtels, Restaurants, Blog, Galerie…)
                arrive module par module dans les phases suivantes.
            </p>
        </div>
    </div>
@endsection
