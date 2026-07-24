@extends('layouts.app', ['title' => __('Actualités'), 'description' => __('Annonces, articles et mises à jour officielles de FINPO 2026.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Actualités'),
    'heading' => __('Toute l\'actualité FINPO'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4">
            @forelse ($posts as $post)
                <div class="col-md-6 col-lg-4 reveal">
                    @include('partials.news-card', ['post' => $post])
                </div>
            @empty
                <p class="fp-muted">{{ __('Aucune actualité pour le moment.') }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    </div>
</section>
@endsection
