@extends('layouts.app', ['title' => __('Inscription — :name', ['name' => $category->name])])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Billetterie'),
    'heading' => __('Billet :name', ['name' => $category->name]),
    'lead' => $category->description,
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7 reveal">
                <div class="fp-card p-4">
                    <h2 class="h4 mb-3">{{ __('Vos informations') }}</h2>
                    <form method="post" action="{{ route('register.store', $category) }}" class="row g-3" id="register-form">
                        @csrf
                        <input type="text" name="company" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="col-md-6">
                            <label class="form-label" for="r-first">{{ __('Prénom') }} *</label>
                            <input id="r-first" name="first_name" class="form-control" required value="{{ old('first_name') }}" autocomplete="given-name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-last">{{ __('Nom') }} *</label>
                            <input id="r-last" name="last_name" class="form-control" required value="{{ old('last_name') }}" autocomplete="family-name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-email">{{ __('Email') }} *</label>
                            <input id="r-email" type="email" name="email" class="form-control" required value="{{ old('email') }}" autocomplete="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-phone">{{ __('Téléphone (WhatsApp)') }}</label>
                            <input id="r-phone" name="phone" class="form-control" value="{{ old('phone') }}" autocomplete="tel" placeholder="+509 …">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-institution">{{ __('Institution / entreprise') }}</label>
                            <input id="r-institution" name="institution" class="form-control" value="{{ old('institution') }}" autocomplete="organization">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-position">{{ __('Fonction') }}</label>
                            <input id="r-position" name="position" class="form-control" value="{{ old('position') }}" autocomplete="organization-title">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-country">{{ __('Pays') }} *</label>
                            <input id="r-country" name="country" class="form-control" required value="{{ old('country', 'Haïti') }}" autocomplete="country-name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-emergency">{{ __('Contact d\'urgence') }}</label>
                            <input id="r-emergency" name="emergency_contact" class="form-control" value="{{ old('emergency_contact') }}" placeholder="{{ __('Nom + téléphone') }}">
                        </div>

                        @unless ($category->isFree())
                            <div class="col-md-6">
                                <label class="form-label" for="r-payment">{{ __('Mode de paiement') }} *</label>
                                <select id="r-payment" name="payment_method" class="form-select" required>
                                    @foreach (config('finpo.payment_methods') as $key => $label)
                                        @continue($key === 'free')
                                        <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="r-coupon">{{ __('Code promo') }}</label>
                                <div class="input-group">
                                    <input id="r-coupon" name="coupon" class="form-control" value="{{ old('coupon') }}" placeholder="EARLYBIRD">
                                    <button class="btn btn-fp-outline" type="button" id="coupon-check">{{ __('Appliquer') }}</button>
                                </div>
                                <div id="coupon-feedback" class="form-text"></div>
                            </div>
                        @else
                            <input type="hidden" name="payment_method" value="free">
                        @endunless

                        <div class="col-12">
                            <button class="btn btn-fp-primary btn-lg w-100">
                                {{ $category->isFree() ? __('Confirmer mon inscription gratuite') : __('Confirmer et obtenir mon billet') }}
                            </button>
                            <p class="fp-muted small mt-2 mb-0">{{ __('En vous inscrivant vous acceptez la charte de participation FINPO. Vos données restent confidentielles.') }}</p>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5 reveal">
                <div class="fp-card p-4 position-sticky" style="top: 100px;">
                    <div class="fp-ticket-top rounded-top" style="background: {{ $category->color }}; height: 6px; margin: -1.5rem -1.5rem 1rem;"></div>
                    <h3 class="h5">{{ __('Récapitulatif') }}</h3>
                    <div class="d-flex justify-content-between py-2" style="border-bottom: 1px dashed var(--fp-card-border);">
                        <span class="fp-muted">{{ __('Billet') }}</span><strong>{{ $category->name }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2" style="border-bottom: 1px dashed var(--fp-card-border);">
                        <span class="fp-muted">{{ __('Dates') }}</span>
                        <strong>{{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d') }}–{{ \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 align-items-center">
                        <span class="fp-muted">{{ __('Total') }}</span>
                        <span class="fp-price fs-4" id="total-price" data-base="{{ $category->price }}">
                            @if ($category->isFree()) {{ __('Gratuit') }} @else {{ number_format($category->price, 0, ',', ' ') }} {{ $category->currency }} @endif
                        </span>
                    </div>
                    @if ($category->benefits)
                        <hr style="border-color: var(--fp-card-border);">
                        @foreach ($category->benefits as $benefit)
                            <div class="fp-benefit">{{ $benefit }}</div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('coupon-check');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const code = document.getElementById('r-coupon').value.trim();
        const feedback = document.getElementById('coupon-feedback');
        const total = document.getElementById('total-price');
        if (!code) { feedback.textContent = ''; return; }
        try {
            const res = await fetch(@json(route('register.coupon')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code: code, category_id: @json($category->id) }),
            });
            const data = await res.json();
            if (data.valid) {
                feedback.textContent = '✓ {{ __('Code appliqué !') }}';
                feedback.style.color = '#7ee0a3';
                total.textContent = data.amount === 0 ? @json(__('Gratuit')) : new Intl.NumberFormat('fr-FR').format(data.amount) + ' {{ $category->currency }}';
            } else {
                feedback.textContent = '✗ {{ __('Code invalide ou expiré.') }}';
                feedback.style.color = '#ff8f8f';
                total.textContent = new Intl.NumberFormat('fr-FR').format(parseInt(total.dataset.base, 10)) + ' {{ $category->currency }}';
            }
        } catch (e) {
            feedback.textContent = @json(__('Vérification impossible pour le moment.'));
        }
    });
})();
</script>
@endpush
@endsection
