<header class="fp-page-head">
    <div class="container">
        <span class="fp-kicker mb-2">{{ $kicker ?? config('finpo.name') }}</span>
        <h1 class="display-5 mb-2">{{ $heading }}</h1>
        @isset($lead)
            <p class="fp-muted mb-0" style="max-width: 720px;">{{ $lead }}</p>
        @endisset
    </div>
</header>
