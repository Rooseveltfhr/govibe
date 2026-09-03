@extends('layouts.public')

@section('title', "{$establishment->name} — Môle-Saint-Nicolas")
@section('meta_description', $establishment->description ?: "{$establishment->name}, Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route($typeSlug === 'hotels' ? 'hotels.index' : 'restaurants.index') }}" class="text-sm text-msn-sea-700 hover:underline">
            &larr; {{ $typeSlug === 'hotels' ? 'Hôtels' : 'Restaurants et bars' }}
        </a>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">{{ $establishment->name }}</h1>
            <x-content-status-badge :status="$establishment->content_status" />
        </div>

        <p class="mt-4 max-w-2xl text-msn-sea-700">{{ $establishment->description ?: '[Information à compléter]' }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-msn-sea-700">Adresse</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $establishment->address ?: '[Information à compléter]' }}</dd>
            </div>
            <div>
                <dt class="text-msn-sea-700">Téléphone</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $establishment->phone ?: '[Information à compléter]' }}</dd>
            </div>
            <div>
                <dt class="text-msn-sea-700">Horaires</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $establishment->opening_hours ?: '[Information à compléter]' }}</dd>
            </div>
            @if ($establishment->price_range)
                <div>
                    <dt class="text-msn-sea-700">Gamme de prix</dt>
                    <dd class="font-semibold text-msn-sea-900">{{ $establishment->price_range }}</dd>
                </div>
            @endif
            @if ($establishment->cuisine_type)
                <div>
                    <dt class="text-msn-sea-700">Cuisine</dt>
                    <dd class="font-semibold text-msn-sea-900">{{ $establishment->cuisine_type }}</dd>
                </div>
            @endif
            @if ($establishment->amenities)
                <div>
                    <dt class="text-msn-sea-700">Équipements</dt>
                    <dd class="font-semibold text-msn-sea-900">{{ $establishment->amenities }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-10 rounded-2xl border border-msn-sand-200 bg-white p-6">
            <h2 class="text-xl font-bold text-msn-sea-900">Réserver</h2>
            <p class="mt-1 text-sm text-msn-sea-700">
                Envoyez une demande — {{ $establishment->name }} vous contactera directement pour confirmer.
                Aucun paiement n'est demandé en ligne.
            </p>

            @if (session('status'))
                <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('bookings.store', $establishment) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Nom complet</label>
                    <input type="text" name="guest_name" required value="{{ old('guest_name') }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Téléphone</label>
                    <input type="text" name="guest_phone" required value="{{ old('guest_phone') }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">E-mail (optionnel)</label>
                    <input type="email" name="guest_email" value="{{ old('guest_email') }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Nombre de personnes</label>
                    <input type="number" name="party_size" min="1" value="{{ old('party_size') }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>

                @if ($establishment->type === 'hotel')
                    <div>
                        <label class="block text-sm font-medium text-msn-sea-900">Arrivée</label>
                        <input type="date" name="starts_on" required value="{{ old('starts_on') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-msn-sea-900">Départ</label>
                        <input type="date" name="ends_on" value="{{ old('ends_on') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300">
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-msn-sea-900">Date</label>
                        <input type="date" name="starts_on" required value="{{ old('starts_on') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-msn-sea-900">Heure</label>
                        <input type="time" name="reservation_time" value="{{ old('reservation_time') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300">
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-msn-sea-900">Message (optionnel)</label>
                    <textarea name="notes" rows="3" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('notes') }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                        Envoyer la demande
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
