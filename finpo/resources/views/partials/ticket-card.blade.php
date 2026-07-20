<article class="fp-card fp-ticket-card">
    <div class="fp-ticket-top" style="background: {{ $category->color }};"></div>
    <div class="p-4 d-flex flex-column h-100">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h3 class="h5 mb-0">{{ $category->name }}</h3>
            @if ($category->remaining() !== null && $category->remaining() <= 25)
                <span class="badge text-bg-danger">{{ __('Plus que :count places', ['count' => $category->remaining()]) }}</span>
            @endif
        </div>
        <div class="fp-price mb-1">
            @if ($category->isFree())
                <span class="fp-gradient-text">{{ __('Gratuit') }}</span>
            @else
                {{ number_format($category->price, 0, ',', ' ') }} <small class="fs-6 fw-normal fp-muted">{{ $category->currency }}</small>
            @endif
        </div>
        <p class="fp-muted small">{{ $category->description }}</p>
        @if ($category->benefits)
            <div class="mb-3">
                @foreach ($category->benefits as $benefit)
                    <div class="fp-benefit">{{ $benefit }}</div>
                @endforeach
            </div>
        @endif
        <div class="mt-auto">
            @if ($category->isOnSale())
                <a href="{{ route('register.form', $category) }}" class="btn btn-fp-primary w-100">{{ __('Réserver') }}</a>
            @else
                <button class="btn btn-fp-outline w-100" disabled>{{ __('Indisponible') }}</button>
            @endif
        </div>
    </div>
</article>
