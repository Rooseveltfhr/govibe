<a href="{{ route('speakers.show', $speaker) }}" class="d-block h-100" style="color: inherit;">
    <article class="fp-card fp-speaker h-100">
        <div class="fp-speaker-photo">
            <img src="{{ $speaker->photo_url ?: 'https://placehold.co/600x640/101a2e/9fb3d1?text='.urlencode($speaker->name) }}"
                 alt="{{ $speaker->name }}" loading="lazy">
        </div>
        <div class="p-3">
            <span class="fp-chip mb-2">{{ $speaker->categoryLabel() }}</span>
            <h3 class="h6 mb-1">{{ $speaker->name }}</h3>
            <p class="fp-muted small mb-0">{{ $speaker->position }}@if($speaker->institution) · {{ $speaker->institution }}@endif</p>
        </div>
    </article>
</a>
