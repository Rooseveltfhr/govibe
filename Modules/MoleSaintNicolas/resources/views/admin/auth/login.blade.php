@extends('layouts.admin')

@section('title', 'Connexion — Administration')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-xl font-bold text-msn-sea-900">Administration Môle-Saint-Nicolas</h1>
            <p class="mt-1 text-sm text-msn-sea-700">Connectez-vous pour gérer le contenu.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-msn-sea-900">E-mail</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-msn-sea-900">Mot de passe</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1 block w-full rounded-lg border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <label class="flex items-center gap-2 text-sm text-msn-sea-700">
                    <input type="checkbox" name="remember" class="rounded border-gray-300">
                    Se souvenir de moi
                </label>
                <button type="submit"
                        class="w-full rounded-lg bg-msn-terracotta-500 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Se connecter
                </button>
            </form>
        </div>
    </div>
@endsection
