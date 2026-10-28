{{-- TAGTOA — quel commerce je suis en train de modifier, visible partout.
     Autonome : styles inclus, pas de JS (élément <details>), même pattern que
     partials/lang.blade.php. Réutilisable partout (en-tête du tableau de bord).

     Signalé après l'audit : un compte peut tenir plusieurs commerces
     (Tenant::switchTo() existe, « Mes commerces » permet d'en changer), mais
     RIEN dans le tableau de bord ne dit sur LEQUEL on travaille en ce moment
     — un marchand qui gère un restaurant ET une quincaillerie pouvait
     ajouter un produit au mauvais commerce sans s'en rendre compte. Affiché
     SEULEMENT quand il y a un choix réel à faire : avec un seul commerce,
     la question ne se pose pas. --}}
@php
    $tgCommerceCourant = \Modules\Tagtoa\App\Models\Business\Business::find(\Modules\Tagtoa\App\Support\Tenant::id());
    $tgAutresCommerces = \Modules\Tagtoa\App\Models\Business\Business::where('account_id', \Modules\Tagtoa\App\Support\Tenant::account())
        ->where('is_active', true)
        ->where('id', '!=', \Modules\Tagtoa\App\Support\Tenant::id())
        ->orderBy('name')
        ->get(['id', 'name']);
@endphp
@if($tgCommerceCourant && $tgAutresCommerces->isNotEmpty())
    <details class="tg-biz">
        <summary aria-label="{{ __('Commerce') }}"><i class="fa-solid fa-shop fl"></i><span class="lb">{{ $tgCommerceCourant->name }}</span><i class="fa-solid fa-chevron-down ch"></i></summary>
        <div class="tg-biz-menu">
            <div class="tg-biz-courant">
                <i class="fa-solid fa-circle-check ck"></i> {{ $tgCommerceCourant->name }}
            </div>
            @foreach($tgAutresCommerces as $b)
                <form method="POST" action="{{ route('tagtoa.business.switch', $b->id) }}">
                    @csrf
                    <button type="submit">{{ $b->name }}</button>
                </form>
            @endforeach
            <a href="{{ route('tagtoa.business.index') }}" class="tg-biz-tous">
                <i class="fa-solid fa-shop"></i> {{ __('Mes commerces') }}
            </a>
        </div>
    </details>
@endif
<style>
    .tg-biz{position:relative;display:inline-block}
    .tg-biz>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1px solid rgba(128,128,128,.28);border-radius:999px;font:600 13px 'Space Grotesk',sans-serif;background:rgba(127,127,127,.06);user-select:none;max-width:180px}
    .tg-biz>summary::-webkit-details-marker{display:none}
    .tg-biz>summary .lb{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .tg-biz>summary .ch{font-size:10px;opacity:.6;flex:0 0 auto}
    .tg-biz[open]>summary .ch{transform:rotate(180deg)}
    .tg-biz-menu{position:absolute;right:0;top:calc(100% + 6px);min-width:200px;background:#fff;color:#0A0A0A;border:1px solid rgba(0,0,0,.1);border-radius:12px;box-shadow:0 12px 34px rgba(0,0,0,.16);padding:6px;z-index:200}
    .tg-biz-courant{display:flex;align-items:center;gap:9px;padding:9px 11px;font:600 14px 'Space Grotesk',sans-serif;color:#2cb809}
    .tg-biz-menu form{margin:0}
    .tg-biz-menu button{width:100%;text-align:left;background:none;border:0;cursor:pointer;display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:9px;font:600 14px 'Space Grotesk',sans-serif;color:#0A0A0A}
    .tg-biz-menu button:hover{background:rgba(44,184,9,.08)}
    .tg-biz-tous{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:9px;font:600 13px 'Space Grotesk',sans-serif;color:var(--muted,#888);border-top:1px solid rgba(0,0,0,.08);margin-top:4px}
    .tg-biz-tous:hover{background:rgba(127,127,127,.08)}
    @media(max-width:520px){.tg-biz>summary .lb{display:none}.tg-biz>summary{max-width:none}}
</style>
