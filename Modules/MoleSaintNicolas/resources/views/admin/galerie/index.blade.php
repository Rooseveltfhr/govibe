@extends('layouts.admin')

@section('title', 'Galerie photos — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-msn-sea-900">Galerie photos</h1>
            <a href="{{ route('admin.galerie.create') }}"
               class="rounded-lg bg-msn-terracotta-500 px-4 py-2 text-sm font-semibold text-white hover:bg-msn-terracotta-600">
                + Ajouter une photo
            </a>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($photos as $photo)
                <div class="overflow-hidden rounded-2xl border border-msn-sand-200 bg-white">
                    <img src="{{ $photo->url }}" alt="{{ $photo->title }}" class="h-40 w-full object-cover">
                    <div class="flex items-center justify-between p-3 text-sm">
                        <div>
                            <p class="font-medium text-msn-sea-900">{{ $photo->title ?: '(sans titre)' }}</p>
                            <p class="text-xs text-msn-sea-700">{{ $photo->category ?: '—' }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.galerie.destroy', $photo) }}" onsubmit="return confirm('Supprimer cette photo ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-msn-sea-700">Aucune photo pour l'instant.</p>
            @endforelse
        </div>
    </div>
@endsection
