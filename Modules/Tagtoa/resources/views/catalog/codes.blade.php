@extends('tagtoa::layouts.dashboard')
@section('title', __('Codes de l\'article'))
@section('page', $article->name.' — '.__('codes'))

@php use Modules\Tagtoa\App\Support\Catalog\Code39; @endphp

@section('content')
<div class="h-row">
    <a href="{{ url()->previous() }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}
    </a>
</div>

<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('Comment ça marche') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:72ch">
        {{ __('Un produit industriel porte déjà un code imprimé : scannez-le une fois ici, et la caisse le reconnaîtra pour toujours.') }}
        <br>
        {{ __('Ce qui n\'a pas de code — pâté, fresco, sachet dlo, manje kwit, artisanat — reçoit une étiquette TAGTOA que vous imprimez et collez. Elle se scanne exactement pareil.') }}
    </p>
</div>

{{-- ---------- Ajouter un code ---------- --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Ajouter un code') }}</h2></div>

    <form method="POST" action="{{ route('tagtoa.catalog.codes.attach') }}">
        @csrf
        <input type="hidden" name="ref" value="{{ $ref }}">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <label style="font-size:12px;color:var(--muted);flex:1;min-width:200px">{{ __('Code-barres') }}
                <input name="code" id="codeField" class="inp" maxlength="64" required
                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                       placeholder="{{ __('Scannez, ou tapez les chiffres') }}">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Libellé (optionnel)') }}
                <input name="label" class="inp" maxlength="60" style="max-width:190px"
                       placeholder="{{ __('ex. carton de 24') }}">
            </label>
            <button type="button" id="scanBtn" class="btn btn-d">
                <i class="fa-solid fa-barcode"></i> {{ __('Scanner') }}
            </button>
            <button class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
        </div>
        @error('code')<p style="color:var(--red);font-size:13px;margin-top:8px">{{ $message }}</p>@enderror
    </form>

    <form method="POST" action="{{ route('tagtoa.catalog.codes.generate') }}" style="margin-top:14px;padding-top:14px;border-top:1px dashed var(--bd)">
        @csrf
        <input type="hidden" name="ref" value="{{ $ref }}">
        <p style="color:var(--muted);font-size:13px;margin-bottom:8px">
            {{ __('Cet article n\'a pas de code-barres imprimé ?') }}
        </p>
        <button class="btn btn-o">
            <i class="fa-solid fa-tag"></i> {{ __('Fabriquer une étiquette TAGTOA') }}
        </button>
    </form>
</div>

{{-- ---------- Les codes de l'article ---------- --}}
<div class="h-row" style="margin-top:24px">
    <h2>{{ __('Codes enregistrés') }} <span style="color:var(--muted);font-weight:400">({{ $codes->count() }})</span></h2>
</div>

@if($codes->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:30px 16px">
        <i class="fa-solid fa-barcode" style="font-size:28px;opacity:.35"></i>
        <p style="margin-top:10px">{{ __('Aucun code pour cet article : il faut encore le chercher dans la grille pour le vendre.') }}</p>
    </div>
@else
    @foreach($codes as $c)
    <div class="card etiq" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <div style="flex:0 0 auto;background:#fff;padding:6px;border:1px solid var(--bd);border-radius:8px">
            @php $svg = Code39::svg($c->code, 2, 54); @endphp
            @if($svg)
                {!! $svg !!}
            @else
                {{-- Un code qui ne se dessine pas en Code 39 (EAN par exemple)
                     reste parfaitement utilisable : il est déjà imprimé sur le
                     produit, on n'a pas à le réimprimer. --}}
                <div style="font:700 16px monospace;padding:16px 10px">{{ $c->code }}</div>
            @endif
        </div>

        <div style="flex:1;min-width:180px">
            <b style="font-family:monospace;font-size:15px">{{ $c->code }}</b>
            @if($c->is_primary)
                <span style="background:rgba(44,184,9,.12);color:#1a7a05;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700;margin-left:6px">{{ __('Principal') }}</span>
            @endif
            <div style="color:var(--muted);font-size:12.5px;margin-top:4px">
                {{ \Modules\Tagtoa\App\Support\Catalog\Barcode::typeLabel($c->code) }}
                @if($c->label) · {{ $c->label }} @endif
            </div>
        </div>

        <div style="display:flex;gap:8px" class="noprint">
            @if($svg)
                <button type="button" class="btn btn-o btn-sm imprimer" data-code="{{ $c->code }}">
                    <i class="fa-solid fa-print"></i> {{ __('Imprimer') }}
                </button>
            @endif
            <form method="POST" action="{{ route('tagtoa.catalog.codes.detach', $c->id) }}"
                  onsubmit="return confirm('{{ __('Retirer ce code ? L\'article ne sera plus trouvé en le scannant.') }}')">
                @csrf @method('DELETE')
                <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button>
            </form>
        </div>
    </div>
    @endforeach
@endif

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script>
window.addEventListener('load', function(){
    var champ = document.getElementById('codeField');

    /* Scanner remplit le champ et s'arrête : attribuer un code est un geste
       délibéré, on ne l'enregistre pas dans le dos du patron. */
    function poser(code){
        champ.value = code;
        champ.focus();
    }

    if(window.TagtoaScanner){
        TagtoaScanner.listenWedge(poser);

        document.getElementById('scanBtn').addEventListener('click', function(){
            TagtoaScanner.open({
                onCode: poser,
                once:   true,
                title:  "{{ __('Scanner le code de l\'article') }}",
                hint:   "{{ __('Visez le code-barres imprimé sur le produit.') }}",
                submit: "{{ __('Utiliser') }}"
            });
        });
    } else {
        document.getElementById('scanBtn').disabled = true;
    }

    /* Impression d'UNE étiquette : une fenêtre autonome, pour ne pas
       imprimer tout le tableau de bord autour. */
    document.querySelectorAll('.imprimer').forEach(function(b){
        b.addEventListener('click', function(){
            var carte = b.closest('.etiq');
            var svg = carte.querySelector('svg');
            if(!svg) return;

            var w = window.open('', '_blank', 'width=420,height=320');
            if(!w) return;
            w.document.write('<!DOCTYPE html><title>' + b.dataset.code + '</title>'
                + '<style>body{margin:0;display:flex;align-items:center;justify-content:center;'
                + 'height:100vh;font-family:system-ui}@page{margin:8mm}</style>'
                + '<div>' + svg.outerHTML + '</div>');
            w.document.close();
            w.focus();
            setTimeout(function(){ w.print(); }, 250);
        });
    });
});
</script>
@endpush
@endsection
