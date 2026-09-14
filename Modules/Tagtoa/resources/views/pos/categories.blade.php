@extends('tagtoa::layouts.dashboard')
@section('title', __('Catégories'))
@section('page', __('Catégories'))

@push('head')
<style>
[hidden]{display:none!important}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.ray{display:flex;gap:11px;align-items:center;padding:11px 0}
.ray + .ray{border-top:1px solid var(--bd)}
.ray .pic{width:42px;height:42px;border-radius:11px;flex:0 0 42px;display:flex;align-items:center;
          justify-content:center;font-size:17px;color:#fff;background:var(--blue)}
.ray .corps{flex:1;min-width:0}
.ray .corps b{font:700 15px var(--fh);display:block}
.ray .corps span{font-size:13px;color:var(--muted)}
.ib{background:none;border:0;cursor:pointer;color:var(--muted);padding:8px 9px;border-radius:8px;font-size:14px}
.ib:hover{background:rgba(0,0,0,.05);color:var(--blk)}
.ib.rouge:hover{background:#fdecea;color:var(--red)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
/* Choisir une icône SANS connaître Font Awesome : on montre les icônes, on ne
   demande pas leur nom. Un marchand ne sait pas ce qu'est « fa-drumstick-bite ». */
.icones{display:grid;grid-template-columns:repeat(auto-fill,minmax(46px,1fr));gap:6px;margin-top:6px}
.icones label{display:flex;align-items:center;justify-content:center;height:42px;border-radius:10px;
      border:1.5px solid var(--bd);cursor:pointer;font-size:16px;color:#555;background:#fff}
.icones input{position:absolute;opacity:0;pointer-events:none}
.icones input:checked + i,.icones label:has(input:checked){border-color:var(--blue);background:var(--blue-pale);color:var(--blue-deep)}
</style>
@endpush

@section('content')
@php
    // Les rayons les plus fréquents d'un commerce haïtien, proposés d'emblée :
    // un marchand qui doit inventer ses rayons devant un champ vide n'en crée
    // aucun, et la grille reste un mur de boutons.
    $suggestions = ['fa-bowl-rice','fa-drumstick-bite','fa-fish','fa-bowl-food','fa-burger',
                    'fa-pizza-slice','fa-mug-hot','fa-bottle-water','fa-beer-mug-empty','fa-wine-glass',
                    'fa-ice-cream','fa-cake-candles','fa-cookie-bite','fa-apple-whole','fa-bread-slice',
                    'fa-leaf','fa-carrot','fa-fire-burner','fa-plate-wheat','fa-tags',
                    'fa-box','fa-shirt','fa-pump-soap','fa-capsules','fa-mobile-screen'];
@endphp

<div class="card">
    <div class="h-row" style="margin-bottom:10px"><h2>{{ __('Nouveau rayon') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.pos.categories.store') }}">
        @csrf
        <div class="pf">
            <div style="grid-column:span 2">
                <label for="cname">{{ __('Nom du rayon') }}</label>
                <input class="ic" id="cname" name="name" required maxlength="80"
                       placeholder="{{ __('Boissons, Plats, Snacks…') }}">
            </div>
            <div>
                <label for="ccolor">{{ __('Couleur') }}</label>
                <input class="ic" id="ccolor" name="color" type="color" value="#2cb809" style="height:38px;padding:3px">
            </div>
        </div>

        <label class="lbl" style="margin-top:12px">{{ __('Icône') }}
            <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">
                — {{ __('facultatif : déduite du nom si vous n\'en choisissez pas') }}
            </span>
        </label>
        <div class="icones">
            @foreach($suggestions as $ic)
                <label title="{{ $ic }}"><input type="radio" name="icon" value="{{ $ic }}"><i class="fa-solid {{ $ic }}"></i></label>
            @endforeach
        </div>

        <button class="btn btn-p" style="margin-top:14px"><i class="fa-solid fa-plus"></i> {{ __('Créer le rayon') }}</button>
    </form>
</div>

<div class="card">
    <div class="h-row">
        <h2>{{ __('Vos rayons') }} <span style="color:var(--muted);font-weight:400">({{ $categories->count() }})</span></h2>
    </div>

    @if($sansRayon > 0)
        <p style="font-size:13px;color:#7a5200;background:#fff5e6;border-radius:10px;padding:10px 12px;margin-bottom:10px">
            <i class="fa-solid fa-circle-info"></i>
            {{ trans_choice('{1}:count article n\'est rangé dans aucun rayon.|[2,*]:count articles ne sont rangés dans aucun rayon.', $sansRayon, ['count' => $sansRayon]) }}
            <a href="{{ route('tagtoa.pos.products') }}" style="color:var(--blue-deep);font-weight:700">{{ __('Les ranger') }}</a>
        </p>
    @endif

    @forelse($categories as $i => $c)
        <div class="ray">
            <span class="pic" style="background:{{ $c->color ?: '#2cb809' }}"><i class="fa-solid {{ $c->icon_class }}"></i></span>
            <span class="corps">
                <b>{{ $c->name }}</b>
                <span>
                    {{ trans_choice('{0}aucun article|{1}:count article|[2,*]:count articles', $c->products_count, ['count' => $c->products_count]) }}
                    @unless($c->is_active) · {{ __('masqué') }} @endunless
                </span>
            </span>
            <button type="button" class="ib modifier" data-id="{{ $c->id }}"><i class="fa-solid fa-pen"></i></button>
            <button type="button" class="ib rouge supprimer" data-id="{{ $c->id }}" data-nom="{{ $c->name }}"
                    data-n="{{ $c->products_count }}"><i class="fa-solid fa-trash"></i></button>
        </div>

        <form method="POST" action="{{ route('tagtoa.pos.categories.update', $c->id) }}"
              class="edition" hidden style="padding:4px 0 16px 53px">
            @csrf @method('PUT')
            <div class="pf">
                <div style="grid-column:span 2">
                    <label>{{ __('Nom') }}</label>
                    <input class="ic" name="name" value="{{ $c->name }}" maxlength="80" required>
                </div>
                <div>
                    <label>{{ __('Ordre') }}</label>
                    <input class="ic" name="sort" type="number" min="0" max="9999" value="{{ $c->sort }}">
                </div>
                <div>
                    <label>{{ __('Couleur') }}</label>
                    <input class="ic" name="color" type="color" value="{{ $c->color ?: '#2cb809' }}" style="height:38px;padding:3px">
                </div>
            </div>
            <div class="icones" style="margin-top:8px">
                @foreach($suggestions as $ic)
                    <label title="{{ $ic }}">
                        <input type="radio" name="icon" value="{{ $ic }}" @checked($c->icon === $ic)>
                        <i class="fa-solid {{ $ic }}"></i>
                    </label>
                @endforeach
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-top:12px;flex-wrap:wrap">
                <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
                <button type="button" class="btn btn-o btn-sm annuler">{{ __('Annuler') }}</button>
                <label style="display:inline-flex;align-items:center;gap:7px;font:600 12.5px var(--fh);color:var(--muted)">
                    <input type="checkbox" name="is_active" value="1" @checked($c->is_active)> {{ __('Visible en caisse') }}
                </label>
            </div>
        </form>
    @empty
        <div class="empty" style="padding:30px 16px">
            <i class="fa-solid fa-folder-open"></i>
            {{ __('Aucun rayon. Créez le premier ci-dessus — la grille de la caisse deviendra lisible.') }}
        </div>
    @endforelse
</div>

<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>
@endsection

@push('scripts')
<script>
window.addEventListener('load', function () {
    var DEL = "{{ url('/tagtoa/pos/categories') }}";

    document.querySelectorAll('.modifier').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = b.closest('.ray').nextElementSibling;
            f.hidden = !f.hidden;
        });
    });
    document.querySelectorAll('.annuler').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('form').hidden = true; });
    });

    /* Supprimer un rayon ne supprime PAS ses articles — on le dit dans la
       question, sinon le marchand croit perdre son catalogue et n'ose pas. */
    document.querySelectorAll('.supprimer').forEach(function (b) {
        b.addEventListener('click', function () {
            var n = Number(b.dataset.n || 0),
                msg = "{{ __('Supprimer le rayon') }} « " + b.dataset.nom + " » ?";
            if (n > 0) msg += "\n\n" + n + " {{ __('article(s) resteront en vente, simplement sans rayon.') }}";
            if (!confirm(msg)) return;
            var f = document.getElementById('delform');
            f.action = DEL + '/' + b.dataset.id;
            f.submit();
        });
    });
});
</script>
@endpush
