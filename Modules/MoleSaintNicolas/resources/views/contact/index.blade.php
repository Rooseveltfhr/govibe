@extends('layouts.public')

@section('title', 'Contact — Môle-Saint-Nicolas')
@section('meta_description', 'Contactez l\'équipe de molesaintnicolas.com : une question, une correction à proposer, un établissement à ajouter.')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">Contact</h1>
        <p class="mt-3 text-msn-ink-700">
            Une question, une correction à proposer sur une page, un établissement ou une activité à ajouter ?
            Écrivez-nous ci-dessous.
        </p>

        <div class="mt-6 rounded-2xl border border-msn-sand-200 bg-white p-4 text-sm text-msn-ink-700">
            <p>[Information à compléter — email/téléphone de contact officiel]</p>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" class="mt-8 space-y-4">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-msn-ink-900">Nom</label>
                    <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                           class="mt-1 block w-full rounded-lg border border-gray-300 bg-white text-msn-ink-900">
                </div>
                <div>
                    <label class="text-sm font-medium text-msn-ink-900">Email</label>
                    <input type="email" name="email" required maxlength="255" value="{{ old('email') }}"
                           class="mt-1 block w-full rounded-lg border border-gray-300 bg-white text-msn-ink-900">
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-msn-ink-900">Téléphone <span class="text-msn-ink-700/70">(optionnel)</span></label>
                <input type="text" name="phone" maxlength="50" value="{{ old('phone') }}"
                       class="mt-1 block w-full rounded-lg border border-gray-300 bg-white text-msn-ink-900">
            </div>

            <div>
                <label class="text-sm font-medium text-msn-ink-900">Message</label>
                <textarea name="message" rows="5" required maxlength="5000"
                          class="mt-1 block w-full rounded-lg border border-gray-300 bg-white text-msn-ink-900">{{ old('message') }}</textarea>
            </div>

            <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                Envoyer le message
            </button>
        </form>
    </div>
@endsection
