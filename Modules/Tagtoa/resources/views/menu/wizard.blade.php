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

    {{-- Aperçu — un téléphone construit depuis ce qui a déjà été saisi plus
         haut (nom, type, logo, couleur, catégories, plats). Aucun nouvel
         appel serveur : tout est déjà dans la page, l'aperçu ne fait que le
         relire — voir wizardApercu(). --}}
    <div class="card" data-step="6" id="wizardApercu">
        <div class="h-row"><h2>{{ __('Aperçu') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">{{ __('Ce que vos clients verront, tel que rempli jusqu\'ici.') }}</p>
        <div class="phone-frame">
            <div class="phone-notch"></div>
            <div class="phone-screen" id="phoneScreen">
                <div class="phone-cover"></div>
                <div class="phone-head">
                    <div class="phone-logo" id="phoneLogo"></div>
                    <div>
                        <div class="phone-name" id="phoneName"></div>
                        <div class="phone-type" id="phoneType"></div>
                    </div>
                </div>
                <div class="phone-tabs" id="phoneTabs"></div>
                <div class="phone-items" id="phoneItems"></div>
            </div>
        </div>
    </div>

    {{-- Publier — le lien, le QR et le code d'intégration n'existent
         qu'APRÈS l'enregistrement (le menu n'a pas encore d'alias tant que
         ce bouton n'a pas été cliqué) : ils vivent sur l'écran d'édition qui
         suit (menu/form.blade.php), jamais ici en avance sur des liens
         qui ne mèneraient nulle part. --}}
    <div class="card" data-step="7" style="text-align:center">
        <i class="fa-solid fa-circle-check" style="font-size:40px;color:#2cb809"></i>
        <h2 style="font-family:var(--fh,inherit);margin:10px 0 4px">{{ __('Votre menu est prêt !') }}</h2>
        <p style="color:var(--muted);font-size:13.5px;max-width:360px;margin:0 auto">
            {{ __('Vérifiez les informations ci-dessus, puis publiez. Le lien à partager, le QR code et le code d\'intégration apparaîtront juste après.') }}
        </p>
        <p style="margin-top:14px">
            <a href="{{ route('tagtoa.menu.dashboard.index') }}" style="font-size:13px;color:var(--muted)">{{ __('Modifier plus tard') }}</a>
        </p>
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

    /* Aperçu — un téléphone, pas un résumé texte. --acc-preview reprend la
       couleur d'accent choisie à l'étape Apparence, posée par wizardApercu(). */
    .phone-frame{width:300px;max-width:100%;margin:14px auto 0;border:10px solid #111;
                 border-radius:36px;overflow:hidden;background:#111;box-shadow:0 14px 30px rgba(0,0,0,.18)}
    .phone-notch{height:22px;background:#111;position:relative}
    .phone-notch::after{content:'';position:absolute;left:50%;top:6px;transform:translateX(-50%);
                 width:70px;height:10px;border-radius:6px;background:#000}
    .phone-screen{--acc-preview:#2cb809;background:#fff;min-height:420px;max-height:520px;overflow-y:auto}
    .phone-cover{height:90px;background:linear-gradient(135deg,var(--acc-preview),#0e5f44)}
    .phone-head{display:flex;gap:10px;align-items:flex-end;padding:0 14px 8px;margin-top:-28px}
    .phone-logo{width:52px;height:52px;border-radius:14px;background:#fff;border:2px solid #fff;
                box-shadow:0 2px 8px rgba(0,0,0,.15);display:flex;align-items:center;justify-content:center;
                font-weight:700;color:var(--acc-preview);overflow:hidden;flex:0 0 auto}
    .phone-logo img{width:100%;height:100%;object-fit:cover}
    .phone-name{font-weight:700;font-size:15px;line-height:1.3}
    .phone-type{font-size:11.5px;color:#888}
    .phone-tabs{display:flex;gap:6px;padding:8px 14px;overflow-x:auto}
    .phone-tab{flex:0 0 auto;padding:5px 11px;border-radius:999px;background:#f2f2f2;
               font-size:11.5px;font-weight:600;white-space:nowrap;color:#555}
    .phone-tab:first-child{background:var(--acc-preview);color:#fff}
    .phone-items{padding:6px 14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .phone-item{border:1px solid #eee;border-radius:12px;overflow:hidden;background:#fff}
    .phone-item-photo{height:64px;background:#f2f2f2;display:flex;align-items:center;justify-content:center;
                      font-weight:700;color:#aaa;overflow:hidden}
    .phone-item-photo img{width:100%;height:100%;object-fit:cover}
    .phone-item-body{padding:7px 8px}
    .phone-item-name{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .phone-item-price{font-size:12px;font-weight:700;color:var(--acc-preview);margin-top:2px}
    .phone-empty{padding:30px 14px;text-align:center;color:#999;font-size:12.5px}
</style>
@push('scripts')
<script>
function wizardApercu(){
    var screen = document.getElementById('phoneScreen');
    if (!screen) { return; }
    var val = function(sel){ var el = document.querySelector(sel); return el ? el.value : ''; };

    var nom = val('input[name="name"]') || '{{ __('(sans nom)') }}';
    var typeSel = document.querySelector('select[name="type"]');
    var typeLabel = typeSel && typeSel.selectedOptions.length ? typeSel.selectedOptions[0].textContent.trim() : '';
    var devise = val('select[name="currency"]') || 'HTG';
    var accent = val('input[name="accent_color"]') || '#2cb809';
    screen.style.setProperty('--acc-preview', accent);

    document.getElementById('phoneName').textContent = nom;
    document.getElementById('phoneType').textContent = typeLabel;

    // Le logo relit l'aperçu déjà posé par previewLogo()/le champ hérité du
    // commerce (voir menu/_form-body.blade.php) — jamais redemandé ici.
    var logoImg = document.getElementById('logoPreview');
    var logoBox = document.getElementById('phoneLogo');
    if (logoImg && logoImg.src && logoImg.style.display !== 'none'){
        logoBox.innerHTML = '<img src="' + logoImg.src + '">';
    } else {
        logoBox.textContent = nom.trim().charAt(0).toUpperCase() || '?';
    }

    var cats = Array.prototype.slice.call(document.querySelectorAll('#cats .catblock'));
    var tabsBox = document.getElementById('phoneTabs');
    tabsBox.innerHTML = '';
    cats.forEach(function(cat){
        var catNom = (cat.querySelector('[name$="[name]"]').value || '').trim();
        if (!catNom) { return; }
        var t = document.createElement('span');
        t.className = 'phone-tab';
        t.textContent = catNom;
        tabsBox.appendChild(t);
    });

    var itemsBox = document.getElementById('phoneItems');
    itemsBox.innerHTML = '';
    var compte = 0;
    cats.forEach(function(cat){
        cat.querySelectorAll('.itemrow').forEach(function(row){
            if (compte >= 6) { return; }
            var itNom = (row.querySelector('[name$="[name]"]').value || '').trim();
            if (!itNom) { return; }
            var prix = row.querySelector('[name$="[price]"]').value || '0';
            var photo = row.querySelector('.itemphoto');
            var photoHtml = (photo && photo.src && photo.style.display !== 'none')
                ? '<img src="' + photo.src + '">'
                : esc(itNom.charAt(0).toUpperCase());
            var carte = document.createElement('div');
            carte.className = 'phone-item';
            carte.innerHTML =
                '<div class="phone-item-photo">' + photoHtml + '</div>' +
                '<div class="phone-item-body">' +
                    '<div class="phone-item-name">' + esc(itNom) + '</div>' +
                    '<div class="phone-item-price">' + esc(prix) + ' ' + esc(devise) + '</div>' +
                '</div>';
            itemsBox.appendChild(carte);
            compte++;
        });
    });
    if (!compte){
        itemsBox.innerHTML = '<div class="phone-empty" style="grid-column:1/-1">{{ __('Aucun plat ajouté pour l\'instant.') }}</div>';
    }
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
