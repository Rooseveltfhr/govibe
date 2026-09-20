@extends('layouts.admin')

@section('title', 'Mot de passe oublié — Administration')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-xl font-bold text-msn-sea-900">Mot de passe oublié</h1>
            <p class="mt-1 text-sm text-msn-sea-700">
                Indiquez l'adresse email de votre compte admin, vous recevrez un lien pour choisir un nouveau mot de passe.
            </p>

            @if (session('status'))
                <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-msn-sea-900">E-mail</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="mt-1 block w-full rounded-lg border border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-msn-terracotta-500 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Envoyer le lien de réinitialisation
                </button>
            </form>

            <a href="{{ route('admin.login') }}" class="mt-4 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                &larr; Retour à la connexion
            </a>
        </div>
    </div>
@endsection
