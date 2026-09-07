@extends('layouts.admin')

@section('title', 'Mon compte — Administration')

@section('content')
    <div class="mx-auto max-w-md px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">Mon compte</h1>
        <p class="mt-1 text-sm text-msn-sea-700">{{ auth()->user()->email }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.profile.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Mot de passe actuel</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Nouveau mot de passe</label>
                <input type="password" name="password" required minlength="10" autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                <p class="mt-1 text-xs text-msn-sea-700">10 caractères minimum.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Confirmer le nouveau mot de passe</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                Mettre à jour le mot de passe
            </button>
        </form>
    </div>
@endsection
