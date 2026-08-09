<a href="{{ route('news.show', $post) }}" class="d-block h-100" style="color: inherit;">
    <article class="fp-card h-100 overflow-hidden">
        <div style="aspect-ratio: 16/9; overflow: hidden;">
            <img src="{{ $post->cover_url ?: 'https://placehold.co/800x450/101a2e/9fb3d1?text=FINPO' }}"
                 alt="" class="w-100 h-100" style="object-fit: cover;" loading="lazy">
        </div>
        <div class="p-3">
            <div class="d-flex gap-2 align-items-center mb-2 small">
                <span class="fp-chip fp-chip-gold">{{ $post->tag }}</span>
                <span class="fp-muted">{{ $post->published_at?->translatedFormat('d F Y') }}</span>
            </div>
            <h3 class="h6 mb-1">{{ $post->title }}</h3>
            <p class="fp-muted small mb-0">{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>
        </div>
    </article>
</a>
