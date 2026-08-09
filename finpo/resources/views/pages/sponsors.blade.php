@extends('layouts.app', ['title' => __('Sponsors'), 'description' => __('Niveaux de sponsoring FINPO 2026 : Title, Diamant, Platine, Or, Argent, Bronze — visibilité maximale garantie.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Sponsors'),
    'heading' => __('Associez votre marque à l\'événement de l\'année'),
    'lead' => __('7 niveaux de sponsoring pour tous les budgets, avec une visibilité mesurée et un rapport d\'impact post-événement.'),
])

<section class="fp-section-tight">
    <div class="container">
        @foreach (config('finpo.sponsor_levels') as $level => $meta)
            @php $group = $sponsors->get($level); @endphp
            @if ($group && $group->isNotEmpty())
                <div class="mb-4 reveal">
                    <h2 class="h5 mb-3" style="color: {{ $meta['color'] }};">{{ $meta['label'] }}</h2>
                    <div class="row g-3">
                        @foreach ($group as $sponsor)
                            <div class="{{ in_array($level, ['title', 'diamond']) ? 'col-md-6' : 'col-6 col-md-4 col-lg-3' }}">
                                <a class="fp-logo-tile h-100" style="min-height: {{ in_array($level, ['title', 'diamond']) ? '160px' : '108px' }};"
                                   href="{{ $sponsor->website ?: '#' }}" target="_blank" rel="noopener" title="{{ $sponsor->name }}">
                                    <img src="{{ $sponsor->logo_url ?: 'https://placehold.co/360x140/0b1220/e8b931?text='.urlencode($sponsor->name) }}" alt="{{ $sponsor->name }}" loading="lazy" @if(in_array($level, ['title', 'diamond'])) style="max-height: 96px;" @endif>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>

<section class="fp-section" style="background: var(--fp-bg-2);" id="packages">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <span class="fp-kicker mb-2 justify-content-center">{{ __('Packages') }}</span>
            <h2 class="h1">{{ __('Comparatif des avantages') }}</h2>
        </div>
        <div class="table-responsive reveal">
            <table class="table fp-table align-middle text-center">
                <thead>
                    <tr>
                        <th class="text-start">{{ __('Avantage') }}</th>
                        @foreach (config('finpo.sponsor_levels') as $level => $meta)
                            <th><span style="color: {{ $meta['color'] }};">{{ $meta['label'] }}</span><br>
                                <small class="fp-muted fw-normal">{{ number_format($meta['price'], 0, ',', ' ') }} HTG</small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        [__('Logo sur le site & supports'), [1,1,1,1,1,1,1]],
                        [__('Badges VIP inclus'), ['12','8','6','4','2','1','—']],
                        [__('Stand expo offert'), ['9x6','6x3','6x3','3x3','3x3','—','—']],
                        [__('Prise de parole en plénière'), [1,1,0,0,0,0,0]],
                        [__('Logo sur la scène principale'), [1,1,1,0,0,0,0]],
                        [__('Vidéo diffusée entre les sessions'), [1,1,1,1,0,0,0]],
                        [__('Mention dans les communiqués'), [1,1,1,1,1,0,0]],
                        [__('Rapport de visibilité post-événement'), [1,1,1,1,1,1,1]],
                    ] as [$benefit, $cells])
                        <tr>
                            <td class="text-start">{{ $benefit }}</td>
                            @foreach ($cells as $cell)
                                <td>@if ($cell === 1) <span style="color: var(--fp-gold);">✔</span> @elseif ($cell === 0 || $cell === '—') <span class="fp-muted">—</span> @else {{ $cell }} @endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="fp-muted small text-center">{{ __('Facture, contrat de sponsoring et certificat officiel remis à chaque sponsor. Suivi de visibilité inclus à partir du niveau Bronze.') }}</p>
    </div>
</section>

<section class="fp-section" id="devenir-sponsor">
    <div class="container" style="max-width: 860px;">
        <div class="fp-card p-4 reveal">
            <h2 class="h3 mb-3">{{ __('Demande de sponsoring') }}</h2>
            <form method="post" action="{{ route('sponsors.apply') }}" class="row g-3">
                @csrf
                <input type="text" name="company" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="col-md-6">
                    <label class="form-label" for="s-name">{{ __('Entreprise / organisation') }} *</label>
                    <input id="s-name" name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="s-level">{{ __('Niveau souhaité') }} *</label>
                    <select id="s-level" name="level" class="form-select" required>
                        @foreach (config('finpo.sponsor_levels') as $key => $meta)
                            <option value="{{ $key }}" @selected(old('level') === $key)>{{ $meta['label'] }} — {{ number_format($meta['price'], 0, ',', ' ') }} HTG</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="s-contact">{{ __('Personne de contact') }} *</label>
                    <input id="s-contact" name="contact_name" class="form-control" required value="{{ old('contact_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="s-email">{{ __('Email') }} *</label>
                    <input id="s-email" type="email" name="contact_email" class="form-control" required value="{{ old('contact_email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="s-phone">{{ __('Téléphone') }}</label>
                    <input id="s-phone" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="s-website">{{ __('Site web') }}</label>
                    <input id="s-website" type="url" name="website" class="form-control" placeholder="https://" value="{{ old('website') }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="s-message">{{ __('Message') }}</label>
                    <textarea id="s-message" name="message" rows="3" class="form-control">{{ old('message') }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-fp-primary">{{ __('Devenir sponsor') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
