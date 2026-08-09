@extends('layouts.app', ['title' => $post->title, 'description' => $post->excerpt, 'image' => $post->cover_url])

@section('content')
<article>
    <header class="fp-page-head">
        <div class="container" style="max-width: 860px;">
            <div class="d-flex gap-2 align-items-center mb-3">
                <span class="fp-chip fp-chip-gold">{{ $post->tag }}</span>
                <span class="fp-muted small">{{ $post->published_at?->translatedFormat('d F Y') }}</span>
            </div>
            <h1 class="display-6">{{ $post->title }}</h1>
        </div>
    </header>

    <div class="container fp-section-tight" style="max-width: 860px;">
        @if ($post->cover_url)
            <img src="{{ $post->cover_url }}" alt="" class="img-fluid rounded-4 w-100 mb-4" style="aspect-ratio: 16/8; object-fit: cover;">
        @endif
        <div class="fs-5" style="white-space: pre-line; line-height: 1.8;">{{ $post->body }}</div>

        <div class="d-flex gap-2 mt-5 fp-no-print">
            <a class="btn btn-fp-outline btn-sm" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener">{{ __('Partager') }} f</a>
            <a class="btn btn-fp-outline btn-sm" href="https://x.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener">𝕏</a>
            <a class="btn btn-fp-outline btn-sm" href="https://wa.me/?text={{ urlencode($post->title.' '.url()->current()) }}" target="_blank" rel="noopener">WhatsApp</a>
        </div>
    </div>

    @if ($others->isNotEmpty())
        <div class="container pb-5">
            <h2 class="h4 mb-3">{{ __('À lire aussi') }}</h2>
            <div class="row g-4">
                @foreach ($others as $other)
                    <div class="col-md-4">
                        @include('partials.news-card', ['post' => $other])
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</article>
@endsection
