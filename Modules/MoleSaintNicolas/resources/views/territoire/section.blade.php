@extends('layouts.public')

@section('title', "{$section->name} — {$commune->name}, Môle-Saint-Nicolas")
@section('meta_description', $section->description ?: "Section communale de {$section->name}, commune de {$commune->name}.")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('territoire.commune', $commune->slug) }}" class="text-sm text-msn-sea-700 hover:underline">
            &larr; {{ $commune->name }}
        </a>

        <div class="mt-3 flex items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Section communale {{ $section->name }}</h1>
            <x-content-status-badge :status="$section->content_status" />
        </div>

        <p class="mt-4 max-w-2xl text-msn-sea-700">{{ $section->description ?: '[Information à compléter]' }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-msn-sea-700">Population</dt>
                <dd class="font-semibold text-msn-sea-900">
                    {{ $section->population ? number_format($section->population, 0, ',', ' ') : '[Information à compléter]' }}
                </dd>
            </div>
            <div>
                <dt class="text-msn-sea-700">Coordonnées GPS</dt>
                <dd class="font-semibold text-msn-sea-900">
                    @if ($section->lat && $section->lng)
                        {{ $section->lat }}, {{ $section->lng }}
                    @else
                        [Information à compléter]
                    @endif
                </dd>
            </div>
        </dl>

        @if ($section->localites->isNotEmpty())
            <h2 class="mt-10 text-xl font-bold text-msn-sea-900">Localités</h2>
            <ul class="mt-3 list-inside list-disc text-msn-sea-700">
                @foreach ($section->localites as $localite)
                    <li>{{ $localite->name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
