@extends('tagtoa::layouts.dashboard')
@section('title', __('Céder mes stands'))
@section('page', __('Céder mes stands'))

@push('head')
<style>
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.lb{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
/* Deux colonnes dès 360 px : quarante numéros en une seule colonne font une
   page qu'on fait défiler sans jamais voir le bouton d'envoi. */
.picks{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:5px;
       max-height:320px;overflow-y:auto;padding:4px;border:1.5px solid var(--bd);border-radius:10px}
.pick{display:flex;gap:7px;align-items:center;padding:7px 8px;border-radius:8px;background:#fafafa;min-width:0}
.pick input{width:16px;height:16px;flex:0 0 auto}
.pick .id{font:700 12.5px monospace;display:block}
.pick .lo{font-size:11px;color:var(--muted);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.code{font:800 21px/1.5 monospace;letter-spacing:.1em;word-break:break-all;
      background:#fff;border:2px dashed #2cb809;border-radius:11px;padding:13px;text-align:center}
.of{display:flex;gap:10px;align-items:center;padding:10px 0;flex-wrap:wrap}
.of + .of{border-top:1px solid var(--bd)}
.of .n{font:700 14px var(--fh);flex:1;min-width:120px}
.tag{border-radius:999px;padding:3px 10px;font:700 11.5px var(--fh);white-space:nowrap}
/* L'explication est repliée : elle se lit UNE fois, et le formulaire ne doit
   pas être repoussé sous le pli à chaque visite suivante. */
.aide summary{cursor:pointer;font:700 13.5px var(--fh);list-style:none;display:flex;gap:8px;align-items:center}
.aide summary::-webkit-details-marker{display:none}
.aide[open] summary{margin-bottom:8px}
.aide p{color:var(--muted);font-size:13.5px;max-width:74ch;margin:0}
.barre{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
</style>
@endpush

@section('content')

{{-- LE CODE, UNE SEULE FOIS.
     Il n'est pas relisible : la base n'en garde que l'empreinte. Le dire
     clairement ici évite qu'on ferme l'onglet en pensant le retrouver. --}}
@if($nouveauCode)
<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-key" style="color:#2cb809"></i> {{ __('Votre code de cession') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin:6px 0 12px;max-width:72ch">
        {{ __('Transmettez-le au repreneur. Il est valable :h heures et ne sert qu\'une fois.', ['h' => $heures]) }}
        <b>{{ __('Il ne sera plus jamais affiché') }}</b>{{ __(' — nous n\'en gardons que l\'empreinte. Perdu, annulez la cession et refaites-en une.') }}
    </p>
    <div class="code">{{ $nouveauCode }}</div>
    <a class="btn btn-p" target="_blank" rel="noopener" style="margin-top:12px"
       href="https://wa.me/?text={{ urlencode(__('Code de cession TAGTOA : ').$nouveauCode) }}">
        <i class="fa-brands fa-whatsapp"></i> {{ __('Envoyer par WhatsApp') }}
    </a>
</div>
@endif

<div class="card">
    <div class="barre" style="margin-bottom:10px">
        <a class="btn btn-p" href="{{ route('tagtoa.stand.transfer.accept.form') }}">
            <i class="fa-solid fa-hand-holding-heart"></i> {{ __('J\'ai reçu un code') }}
        </a>
        <a class="btn btn-o" href="{{ route('tagtoa.stand.index') }}">
            <i class="fa-solid fa-sign-hanging"></i> {{ __('Mes stands') }}
        </a>
    </div>

    <details class="aide">
        <summary>
            <i class="fa-solid fa-circle-info" style="color:var(--amber)"></i>
            {{ __('Quand on vend son commerce') }}
        </summary>
        <p>
            {{ __('Vos stands restent gravés : on ne réimprime rien. Vous émettez un code, le repreneur le saisit, et les stands passent à son commerce.') }}
            <br>
            {{-- Le dire, parce que c'est la première inquiétude du marchand qui
                 vend : « mes tables vont-elles afficher une erreur pendant la
                 négociation ? » --}}
            <b>{{ __('Vos QR continuent de fonctionner pendant tout ce temps') }}</b>{{ __(' : le client attablé ne voit aucune différence.') }}
            <br>
            {{ __('Gratter à nouveau le panneau ne sert à rien : un stand déjà activé ne se réclame plus. C\'est ce qui empêche un ancien propriétaire de reprendre vos stands après la vente.') }}
        </p>
    </details>
</div>

@if($cessibles->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:30px 16px">
        <i class="fa-solid fa-sign-hanging" style="font-size:28px;opacity:.35"></i>
        <p style="margin-top:10px">{{ __('Aucun stand actif à céder pour le moment.') }}</p>
    </div>
@else
<form method="POST" action="{{ route('tagtoa.stand.transfer.store') }}" class="card">
    @csrf
    <div class="h-row" style="margin-bottom:4px"><h2>{{ __('Choisir les stands à céder') }}</h2></div>
    <div class="barre" style="margin-bottom:9px">
        <p style="color:var(--muted);font-size:12.5px;flex:1;min-width:180px;margin:0">
            {{ __('Un seul code couvre toute la sélection.') }}
        </p>
        {{-- « Je vends tout » est le cas le plus fréquent : quarante cases à
             cocher une par une feraient renoncer avant la dixième. --}}
        <label style="display:flex;gap:6px;align-items:center;font:600 12.5px var(--fh);white-space:nowrap">
            <input type="checkbox" id="tout" style="width:16px;height:16px">
            {{ __('Tout sélectionner') }} ({{ $cessibles->count() }})
        </label>
    </div>

    <div class="picks" id="picks">
        @foreach($cessibles as $s)
        <label class="pick">
            <input type="checkbox" name="stands[]" value="{{ $s->id }}">
            <span style="min-width:0">
                <span class="id">{{ $s->public_id }}</span>
                <span class="lo">{{ $s->location_label ?: __('sans emplacement') }}</span>
            </span>
        </label>
        @endforeach
    </div>

    <div style="margin-top:11px">
        <label class="lb" for="note">{{ __('Pour vous en souvenir') }}</label>
        <input class="ic" id="note" name="note" maxlength="160"
               placeholder="{{ __('ex. vente du bar à Jean-Claude') }}">
        <p style="color:var(--muted);font-size:11.5px;margin-top:4px">
            {{ __('Cette note reste chez vous : le repreneur ne la voit pas.') }}
        </p>
    </div>

    <button class="btn btn-p" style="margin-top:13px">
        <i class="fa-solid fa-paper-plane"></i> {{ __('Émettre le code de cession') }}
    </button>
</form>

<script>
/* Tout cocher. ES5, sans dépendance : cette page doit s'ouvrir sur le
   téléphone d'entrée de gamme d'un marchand, connexion comprise. */
(function () {
    var tout = document.getElementById('tout'), zone = document.getElementById('picks');
    if (!tout || !zone) { return; }
    tout.addEventListener('change', function () {
        var cases = zone.querySelectorAll('input[type=checkbox]');
        for (var i = 0; i < cases.length; i++) { cases[i].checked = tout.checked; }
    });
})();
</script>
@endif

@if($offres->isNotEmpty())
<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('Vos cessions') }}</h2></div>
    @foreach($offres as $o)
    <div class="of">
        <span class="n">
            {{ trans_choice('{1}:count stand|[2,*]:count stands', $o->items_count, ['count' => $o->items_count]) }}
            @if($o->note)
                <span style="display:block;font:400 12px var(--fb);color:var(--muted)">{{ $o->note }}</span>
            @endif
            <span style="display:block;font:400 11.5px var(--fb);color:var(--muted)">
                {{ $o->created_at?->diffForHumans() }}
            </span>
        </span>

        @php($enAttente = $o->isPending())
        <span class="tag" style="{{ $enAttente
            ? 'background:rgba(44,184,9,.12);color:#1a7a05'
            : ($o->accepted_at ? 'background:var(--blue-pale);color:var(--blue-deep)' : 'background:#f2f2f2;color:#666') }}">
            {{ $o->status_label }}
        </span>

        @if($enAttente)
        <form method="POST" action="{{ route('tagtoa.stand.transfer.cancel', $o->id) }}"
              onsubmit="return confirm('{{ __('Annuler cette cession ? Le code cessera de fonctionner.') }}')">
            @csrf @method('DELETE')
            <button class="btn btn-o btn-sm"><i class="fa-solid fa-xmark"></i> {{ __('Annuler') }}</button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endif

@endsection
