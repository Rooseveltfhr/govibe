@extends('layouts.public')

@section('title', $post->title.' — Môle-Saint-Nicolas')
@section('meta_description', $post->excerpt ?: $post->title)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">{{ $post->title }}</h1>
            <x-content-status-badge :status="$post->content_status" />
        </div>
        <p class="mt-1 text-xs uppercase tracking-wide text-msn-sea-700/70">{{ $post->published_at->format('d/m/Y') }}</p>

        <div class="mt-8 space-y-4 text-msn-sea-700
                    [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-msn-sea-900
                    [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-msn-sea-900
                    [&_p]:leading-relaxed
                    [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6
                    [&_a]:font-semibold [&_a]:text-msn-terracotta-500 [&_a]:hover:underline">
            {!! $post->body !!}
        </div>

        <a href="{{ route('actualites.index') }}" class="mt-8 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
            &larr; Toutes les actualités
        </a>
    </div>
@endsection
