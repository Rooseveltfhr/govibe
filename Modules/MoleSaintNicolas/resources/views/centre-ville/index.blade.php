@extends('layouts.public')

@section('title', $page->title.' — Môle-Saint-Nicolas')
@section('meta_description', $page->meta_description ?: $page->title)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">{{ $page->title }}</h1>
            <x-content-status-badge :status="$page->content_status" />
        </div>

        <div class="mt-8 space-y-4 text-msn-ink-700
                    [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-msn-ink-900
                    [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-msn-ink-900
                    [&_p]:leading-relaxed
                    [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6
                    [&_a]:font-semibold [&_a]:text-msn-terracotta-500 [&_a]:hover:underline">
            {!! $page->body !!}
        </div>

        @if ($commune?->lat && $commune?->lng)
            <div class="mt-10">
                <h2 class="mb-3 text-lg font-semibold text-msn-ink-900">Localisation</h2>
                <x-leaflet-map
                    :markers="[['lat' => (float) $commune->lat, 'lng' => (float) $commune->lng, 'label' => 'Centre-ville de Môle-Saint-Nicolas']]"
                    :center-lat="(float) $commune->lat"
                    :center-lng="(float) $commune->lng"
                    :zoom="14"
                />
            </div>
        @endif
    </div>
@endsection
