@extends('layouts.admin')

@section('title', 'Réinitialiser le mot de passe — Administration')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-xl font-bold text-msn-sea-900">Nouveau mot de passe</h1>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label for="email" class="block text-sm font-medium text-msn-sea-900">E-mail</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email', $email) }}"
                           class="mt-1 block w-full rounded-lg border border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-msn-sea-900">Nouveau mot de passe</label>
                    <input id="password" name="password" type="password" required minlength="10"
                           class="mt-1 block w-full rounded-lg border border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-msn-sea-900">Confirmer le mot de passe</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="10"
                           class="mt-1 block w-full rounded-lg border border-gray-300 focus:border-msn-sea-500 focus:ring-msn-sea-500">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-msn-terracotta-500 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Réinitialiser le mot de passe
                </button>
            </form>
        </div>
    </div>
@endsection
