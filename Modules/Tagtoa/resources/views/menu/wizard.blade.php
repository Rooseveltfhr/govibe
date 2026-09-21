@extends('tagtoa::layouts.dashboard')
@php $editing = $menu->exists; @endphp
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
    <div class="wizard-nav">
        @foreach($wizardSteps as $n => $label)
            <button type="button" class="wizard-pill" data-goto="{{ $n }}" onclick="wizardGo({{ $n }})">
                <span class="wizard-pill-n">{{ $n }}</span> {{ $label }}
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
    .wizard-nav{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
    .wizard-pill{border:1.5px solid var(--bd);border-radius:999px;background:#fff;padding:6px 12px;
                 font-size:12.5px;font-weight:600;cursor:pointer;color:var(--muted);display:flex;align-items:center;gap:6px}
    .wizard-pill-n{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;
                   border-radius:50%;background:var(--bd);font-size:11px}
    .wizard-pill.active{border-color:#2cb809;color:#0e5f44;background:rgba(44,184,9,.09)}
    .wizard-pill.active .wizard-pill-n{background:#2cb809;color:#fff}
    .wizard-footer{display:none;justify-content:space-between;align-items:center;margin-top:16px}
    .wizard-footer.active{display:flex}
    /* L'étape « Catégories » ne montre que les rayons : les articles se
       saisissent à l'étape suivante, sur le même bloc — un plat n'existe pas
       sans catégorie, donc rien à dupliquer entre les deux étapes. */
    .wizard-shell[data-step="4"] .items,
    .wizard-shell[data-step="4"] .tt-additem { display: none; }
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
        p.classList.toggle('active', p.getAttribute('data-goto') === String(n));
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
