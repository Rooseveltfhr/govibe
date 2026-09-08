@extends('layouts.public')

@section('title', 'Galerie photos — Môle-Saint-Nicolas')
@section('meta_description', "Galerie photos de Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">Galerie photos</h1>

        @if ($categories->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('galerie.index') }}"
                   class="rounded-full border px-4 py-1.5 font-semibold {{ $category ? 'border-msn-sand-200 text-msn-ink-900' : 'border-msn-terracotta-500 bg-msn-terracotta-500 text-white' }}">
                    Tout
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('galerie.index', ['categorie' => $cat]) }}"
                       class="rounded-full border px-4 py-1.5 font-semibold {{ $category === $cat ? 'border-msn-terracotta-500 bg-msn-terracotta-500 text-white' : 'border-msn-sand-200 text-msn-ink-900' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($photos as $photo)
                <figure class="overflow-hidden rounded-2xl border border-msn-sand-200 bg-white">
                    <img src="{{ $photo->url }}" alt="{{ $photo->title ?: 'Môle-Saint-Nicolas' }}" class="h-48 w-full object-cover" loading="lazy">
                    @if ($photo->title)
                        <figcaption class="p-3 text-sm text-msn-ink-700">{{ $photo->title }}</figcaption>
                    @endif
                </figure>
            @empty
                <div class="col-span-full">
                    <x-photo-placeholder label="Galerie à venir" class="h-48 w-full rounded-2xl" />
                    <p class="mt-3 text-sm text-msn-ink-700">[Information à compléter — aucune photo ajoutée pour l'instant]</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
