@extends('tagtoa::layouts.dashboard')
@section('title', $stand->public_id)
@section('page', $stand->public_id)

@push('head')
<style>
[hidden]{display:none!important}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.fiche{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
.fiche div b{display:block;font:600 11px var(--fh);color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
.fiche div span{font-size:14.5px;word-break:break-word}
.ev{display:flex;gap:11px;padding:10px 0;flex-wrap:wrap;align-items:baseline}
.ev + .ev{border-top:1px solid var(--bd)}
.ev .q{font:700 13px var(--fh);flex:0 0 auto}
.ev .w{flex:1;min-width:150px;font-size:13px;color:var(--muted)}
.ev .d{font-size:11.5px;color:var(--muted);white-space:nowrap}
.danger{border-left:4px solid var(--red)}
</style>
@endpush

@section('content')

<div class="card">
    <div class="h-row" style="margin-bottom:10px">
        <h2>{{ __('La fiche') }}</h2>
        <a class="btn btn-o btn-sm" href="{{ route('tagtoa.superadmin.stands') }}">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Le parc') }}
        </a>
    </div>
    <div class="fiche">
        <div><b>{{ __('Objet') }}</b><span>{{ $etatsP[$stand->physical_state] ?? '?' }}</span></div>
        <div><b>{{ __('Identité') }}</b><span>{{ $etatsN[$stand->digital_state] ?? '?' }}</span></div>
        <div><b>{{ __('Lot') }}</b><span>{{ $stand->batch->code ?? '—' }}</span></div>
        <div><b>{{ __('Commerce') }}</b>
            <span>{{ $commerce?->name ?? ($stand->tenant_id ?: __('aucun')) }}</span></div>
        <div><b>{{ __('Détenteur') }}</b>
            <span>{{ $detenteur?->name ?? __('TAGTOA') }}</span></div>
        <div><b>{{ __('Emplacement') }}</b><span>{{ $stand->location_label ?: '—' }}</span></div>
        <div><b>{{ __('Réclamé le') }}</b><span>{{ optional($stand->claimed_at)->format('d/m/Y H:i') ?: '—' }}</span></div>
        <div><b>{{ __('Dernier scan') }}</b>
            <span>{{ optional($stand->last_scanned_at)->format('d/m/Y H:i') ?: __('jamais') }}</span></div>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-top:12px;max-width:74ch">
        {{-- Le dire sur la fiche elle-même : c'est ici qu'on serait tenté de
             chercher le code, l'objet en main, avec un marchand au téléphone. --}}
        <i class="fa-solid fa-lock"></i>
        {{ __('Le code d\'activation de ce stand n\'est pas consultable — ni ici, ni ailleurs dans l\'application. Il vit sous le panneau à gratter, et dans le fichier de frappe.') }}
    </p>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('L\'objet') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:10px">
        {{ __('Déclarer un stand perdu ou endommagé RÉVOQUE son code : il ne pourra plus jamais être activé. C\'est la seule protection réelle contre un stand volé — on ne peut pas empêcher qu\'on le prenne, on peut faire qu\'il ne serve à rien.') }}
    </p>
    <form method="POST" action="{{ route('tagtoa.superadmin.stand.physical', $stand->public_id) }}">
        @csrf @method('PUT')
        <div class="pf">
            <div>
                <label for="ep">{{ __('Nouvel état') }}</label>
                <select class="ic" id="ep" name="etat">
                    @foreach($etatsP as $cle => $label)
                        <option value="{{ $cle }}" @selected($stand->physical_state === $cle)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:span 2">
                <label for="mp">{{ __('Motif') }}</label>
                <input class="ic" id="mp" name="motif" maxlength="200"
                       placeholder="{{ __('ex. carton 41-80 reçu incomplet chez Wilner') }}">
            </div>
        </div>
        <button class="btn btn-p" style="margin-top:11px">
            <i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('L\'identité numérique') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:10px">
        {{-- Ce que le CLIENT voit compte plus que ce que le tableau de bord
             affiche : le stand est posé sur une table, devant quelqu'un qui a
             faim. --}}
        {{ __('Un stand suspendu ne rend ni une erreur ni une page de paiement : le client voit une page sobre. On prévient le commerçant, pas son client.') }}
    </p>
    <form method="POST" action="{{ route('tagtoa.superadmin.stand.digital', $stand->public_id) }}">
        @csrf @method('PUT')
        <div class="pf">
            <div>
                <label for="en">{{ __('Nouvel état') }}</label>
                <select class="ic" id="en" name="etat">
                    <option value="active" @selected($stand->digital_state === 'active')>{{ __('Actif') }}</option>
                    <option value="suspended" @selected($stand->digital_state === 'suspended')>{{ __('Suspendu') }}</option>
                    <option value="revoked" @selected($stand->digital_state === 'revoked')>{{ __('Révoqué') }}</option>
                </select>
            </div>
            <div style="grid-column:span 2">
                <label for="mn">{{ __('Motif') }}</label>
                <input class="ic" id="mn" name="motif" maxlength="200"
                       placeholder="{{ __('ex. abonnement impayé depuis trois mois') }}">
            </div>
        </div>
        <button class="btn btn-p" style="margin-top:11px">
            <i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

{{-- LE POUVOIR LE PLUS DANGEREUX DE LA PLATEFORME.
     Le fondateur peut prendre les stands d'un commerce vivant et les donner à
     son concurrent. Rien dans le code ne peut l'en empêcher — c'est un choix
     humain. Ce qui peut être fait, c'est le rendre impossible à nier. --}}
<div class="card danger">
    <div class="h-row" style="margin-bottom:6px">
        <h2 style="color:var(--red)">{{ __('Cession forcée') }}</h2>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:10px;max-width:74ch">
        {{ __('À n\'utiliser que quand le propriétaire a disparu : compte mort, commerce fermé sans prévenir, personne décédée. Un compte que plus personne n\'ouvre tient sinon ses stands en otage pour toujours.') }}
        <br>
        <b>{{ __('Le motif est obligatoire et reste au journal') }}</b>{{ __(' — avec les deux commerces, votre nom et votre adresse. C\'est ce qui rend l\'acte impossible à nier six mois plus tard.') }}
    </p>
    <form method="POST" action="{{ route('tagtoa.superadmin.stand.force', $stand->public_id) }}"
          onsubmit="return confirm('{{ __('Déplacer ce stand sans l\'accord de son propriétaire ?') }}')">
        @csrf @method('PUT')
        <div class="pf">
            <div style="grid-column:span 2">
                <label for="tb">{{ __('Vers le commerce') }}</label>
                <input class="ic" id="tb" name="to_business_id" required maxlength="64"
                       style="font-family:monospace" placeholder="biz_...">
            </div>
            <div style="grid-column:span 2">
                <label for="mf">{{ __('Motif écrit (obligatoire)') }}</label>
                <input class="ic" id="mf" name="motif" required
                       minlength="{{ \Modules\Tagtoa\App\Services\Stand\StandAdminService::MOTIF_MIN }}" maxlength="200"
                       placeholder="{{ __('ex. propriétaire décédé, repris par sa fille — acte du 12/09') }}">
            </div>
        </div>
        <button class="btn" style="margin-top:11px;background:var(--red);color:#fff">
            <i class="fa-solid fa-right-left"></i> {{ __('Forcer la cession') }}
        </button>
    </form>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('Toute son histoire') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:8px">
        {{-- Le journal ne se réécrit jamais : une ligne modifiée effacerait
             exactement la trace qu'on cherche. --}}
        {{ __('Écrite une fois, jamais modifiée. C\'est ce qui tranche « ce stand est à moi ».') }}
    </p>
    @forelse($histoire as $e)
        <div class="ev">
            <span class="q">{{ $e->event }}</span>
            <span class="w">
                {{ $e->from_state ? $e->from_state.' → '.$e->to_state : $e->to_state }}
                @if($e->actor_name) · {{ $e->actor_name }} @endif
                @if(is_array($e->meta) && ($e->meta['motif'] ?? null))
                    <span style="display:block;color:var(--blk)">« {{ $e->meta['motif'] }} »</span>
                @endif
                @if(is_array($e->meta) && ($e->meta['force'] ?? false))
                    <span style="display:block;color:var(--red);font-weight:700">
                        {{ __('CESSION FORCÉE') }} : {{ $e->meta['from'] ?: __('aucun') }} → {{ $e->meta['to'] }}
                    </span>
                @endif
            </span>
            <span class="d">{{ optional($e->created_at)->format('d/m/Y H:i') }}{{ $e->ip ? ' · '.$e->ip : '' }}</span>
        </div>
    @empty
        <p style="color:var(--muted);padding:14px 0">{{ __('Aucun événement.') }}</p>
    @endforelse
</div>

@endsection
