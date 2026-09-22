@extends('tagtoa::layouts.dashboard')
@php $editing = $menu->exists; $isWizard = true; @endphp
@section('title', __('Nouveau menu — assistant'))
@section('page', __('Nouveau menu — assistant'))

@section('content')
@php
    $wizardSteps = [
        1 => __('Établissement'),
        2 => __('Info'),
        3 => __('Paramètres'),
        4 => __('Catégories'),
        5 => __('Plats'),
        6 => __('Aperçu'),
        7 => __('Publier'),
    ];
@endphp
<div class="wizard-shell" data-step="1">
    <div class="wizard-header">
        <div>
            <h1 class="wizard-title">{{ __('Créer votre menu en quelques étapes') }}</h1>
            <p class="wizard-subtitle">{{ __('Restaurant, Hôtel, Bar, Café, etc. — Simple, rapide et professionnel.') }}</p>
        </div>
        <span class="wizard-badge">{{ __("De l'ajout de votre établissement à la publication de votre menu.") }}</span>
    </div>
    <div class="wizard-nav">
        @foreach($wizardSteps as $n => $label)
            <button type="button" class="wizard-pill" data-goto="{{ $n }}" onclick="wizardGo({{ $n }})">
                <span class="wizard-pill-n">{{ $n }}</span>
                <span class="wizard-pill-label">{{ $label }}</span>
            </button>
        @endforeach
    </div>
    <p style="margin-top:2px">
        <a href="{{ route('tagtoa.menu.dashboard.create') }}" style="font-size:13px;color:var(--muted)">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Revenir au formulaire classique') }}
        </a>
    </p>

    @include('tagtoa::menu._form-body')

    {{-- Aperçu — résumé en lecture seule construit depuis ce qui a déjà été
         saisi plus haut. Aucun nouvel appel serveur : tout est déjà dans la
         page, l'aperçu ne fait que le relire. --}}
    <div class="card" data-step="6" id="wizardApercu">
        <div class="h-row"><h2>{{ __('Aperçu') }}</h2></div>
        <div id="wizardApercuBody" style="font-size:14px;line-height:1.7"></div>
    </div>

    <div class="wizard-footer" data-step="1">
        <span></span>
        <button type="button" class="btn btn-p" onclick="wizardGo(2)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="2">
        <button type="button" class="btn btn-o" onclick="wizardGo(1)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <button type="button" class="btn btn-p" onclick="wizardGo(3)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="3">
        <button type="button" class="btn btn-o" onclick="wizardGo(2)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <button type="button" class="btn btn-p" onclick="wizardGo(4)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="4">
        <button type="button" class="btn btn-o" onclick="wizardGo(3)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <button type="button" class="btn btn-p" onclick="wizardGo(5)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="5">
        <button type="button" class="btn btn-o" onclick="wizardGo(4)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <button type="button" class="btn btn-p" onclick="wizardGo(6)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="6">
        <button type="button" class="btn btn-o" onclick="wizardGo(5)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <button type="button" class="btn btn-p" onclick="wizardGo(7)">{{ __('Suivant') }} <i class="fa-solid fa-arrow-right"></i></button>
    </div>
    <div class="wizard-footer" data-step="7">
        <button type="button" class="btn btn-o" onclick="wizardGo(6)"><i class="fa-solid fa-arrow-left"></i> {{ __('Précédent') }}</button>
        <span></span>
    </div>
</div>

<style>
    .wizard-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}
    .wizard-title{font:700 22px var(--fh,inherit);margin:0}
    .wizard-subtitle{color:var(--muted);font-size:13.5px;margin:4px 0 0}
    .wizard-badge{background:#fde8ea;color:#b3324a;font-size:12px;font-weight:600;padding:8px 14px;
                  border-radius:10px;max-width:280px;line-height:1.4}

    /* Étapes reliées par une ligne, comme un stepper d'app mobile — la même
       liste de noms que $wizardSteps, juste un autre habillage. */
    .wizard-nav{display:flex;align-items:flex-start;margin-bottom:18px;overflow-x:auto;padding:4px 0 8px}
    .wizard-pill{display:flex;flex-direction:column;align-items:center;gap:6px;background:none;border:none;
                 cursor:pointer;color:var(--muted);flex:1;min-width:76px;position:relative;padding:0}
    .wizard-pill-n{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;
                   border-radius:50%;background:#fff;border:2px solid var(--bd);font-size:13px;font-weight:700;
                   color:var(--muted);position:relative;z-index:1}
    .wizard-pill-label{font-size:11px;font-weight:600;text-align:center;white-space:nowrap}
    .wizard-pill:not(:last-child)::after{content:'';position:absolute;top:15px;left:calc(50% + 19px);
                 width:calc(100% - 19px);height:2px;background:var(--bd);z-index:0}
    .wizard-pill.done .wizard-pill-n{border-color:#2cb809;color:#2cb809}
    .wizard-pill.done:not(:last-child)::after{background:#2cb809}
    .wizard-pill.active .wizard-pill-n{background:#2cb809;border-color:#2cb809;color:#fff}
    .wizard-pill.active .wizard-pill-label{color:#0e5f44}

    .wizard-footer{display:none;justify-content:space-between;align-items:center;margin-top:16px}
    .wizard-footer.active{display:flex}
    /* L'étape « Catégories » ne montre que les rayons : les articles se
       saisissent à l'étape suivante, sur le même bloc — un plat n'existe pas
       sans catégorie, donc rien à dupliquer entre les deux étapes. */
    .wizard-shell[data-step="4"] .items,
    .wizard-shell[data-step="4"] .tt-additem { display: none; }

    /* Type d'établissement : la grille de cartes remplace le <select> — pas
       à côté, à sa place. Le <select> reste dans le DOM (soumission du
       formulaire + applyProfile()), juste masqué visuellement ici. */
    .wizard-shell .type-select-wrap{display:none}
    .type-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-top:6px}
    .type-card{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px 10px;
               border:1.5px solid var(--bd);border-radius:14px;background:#fff;cursor:pointer;
               font:inherit;color:inherit;position:relative}
    .type-card i{font-size:22px;color:var(--muted)}
    .type-card-label{font-size:12.5px;font-weight:600;text-align:center}
    .type-card.selected{border-color:#2cb809;background:rgba(44,184,9,.07)}
    .type-card.selected i{color:#2cb809}
    .type-card.selected::after{content:'\f00c';font-family:'Font Awesome 6 Free';font-weight:900;
               position:absolute;top:8px;right:8px;width:18px;height:18px;border-radius:50%;
               background:#2cb809;color:#fff;font-size:10px;display:flex;align-items:center;justify-content:center}
</style>
@push('scripts')
<script>
function wizardApercu(){
    var box = document.getElementById('wizardApercuBody');
    if (!box) { return; }
    var val = function(sel){ var el = document.querySelector(sel); return el ? el.value : ''; };
    var nom = val('input[name="name"]') || '{{ __('(sans nom)') }}';
    var typeSel = document.querySelector('select[name="type"]');
    var typeLabel = typeSel && typeSel.selectedOptions.length ? typeSel.selectedOptions[0].textContent : '';
    var devise = val('select[name="currency"]');
    var nbCats = document.querySelectorAll('#cats .catblock').length;
    var nbItems = document.querySelectorAll('#cats .itemrow').length;

    box.innerHTML =
        '<p><strong>' + esc(nom) + '</strong>' + (typeLabel ? ' — ' + esc(typeLabel.trim()) : '') + '</p>' +
        '<p>{{ __('Devise') }} : ' + esc(devise || '—') + '</p>' +
        '<p>' + nbCats + ' {{ __('catégorie(s)') }}, ' + nbItems + ' {{ __('article(s)') }}</p>';
}

function wizardGo(n){
    var shell = document.querySelector('.wizard-shell');
    shell.setAttribute('data-step', n);

    document.querySelectorAll('.wizard-pill').forEach(function(p){
        var etape = Number(p.getAttribute('data-goto'));
        p.classList.toggle('active', etape === n);
        p.classList.toggle('done', etape < n);
    });
    document.querySelectorAll('.wizard-footer').forEach(function(f){
        f.classList.toggle('active', f.getAttribute('data-step') === String(n));
    });
    document.querySelectorAll('[data-step]').forEach(function(el){
        if (el === shell || el.classList.contains('wizard-footer')) { return; }
        var steps = el.getAttribute('data-step').split(' ');
        el.style.display = steps.indexOf(String(n)) !== -1 ? '' : 'none';
    });

    if (n === 6) { wizardApercu(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function(){ wizardGo(1); });
</script>
@endpush
@endsection
