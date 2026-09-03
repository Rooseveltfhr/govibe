@extends('tagtoa::layouts.dashboard')
@section('title', __('Passerelles de paiement'))
@section('page', __('Passerelles de paiement'))

@section('content')
{{-- Réservé au fondateur. Aucun identifiant n'est jamais réaffiché : on ne
     montre que sa PROVENANCE (saisi ici / défini sur le serveur / absent). --}}

@php
    $ready = collect($drivers)->where('ready', true)->count();
    $total = count($drivers);
@endphp

<div class="gw-hero">
    <div>
        <span class="gw-kicker">{{ __('Super-admin') }}</span>
        <h2>{{ __('Vos passerelles') }}</h2>
        <p>{{ __('Branchez vos identifiants API ici — plus besoin de toucher au serveur.') }}</p>
    </div>
    <div class="gw-score">
        <span class="n">{{ $ready }}<em>/{{ $total }}</em></span>
        <span class="l">{{ __('prêtes à encaisser') }}</span>
    </div>
</div>

<div class="h-row" style="gap:8px;margin-bottom:18px">
    <a href="{{ route('tagtoa.superadmin.index') }}" class="btn btn-o btn-sm"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <a href="{{ route('tagtoa.superadmin.status') }}" class="btn btn-o btn-sm"><i class="fa-solid fa-server"></i> {{ __('État système') }}</a>
</div>

{{-- ============ 1. IDENTIFIANTS API ============ --}}
<h3 class="gw-h">{{ __('1. Identifiants API') }}</h3>
<p class="gw-sub">{{ __('Saisissez les clés fournies par chaque fournisseur. Elles sont chiffrées en base et ne seront plus jamais réaffichées.') }}</p>

<div class="gw-grid">
    @foreach($drivers as $driver => $d)
        @php $spec = $d['fields']; @endphp
        <form method="POST" action="{{ route('tagtoa.superadmin.gateways.credentials', $driver) }}" class="gw-card {{ $d['ready'] ? 'is-ready' : '' }}">
            @csrf
            <div class="gw-card-head">
                <div class="gw-logo">{{ strtoupper(substr($d['label'], 0, 2)) }}</div>
                <div style="flex:1;min-width:0">
                    <b>{{ $d['label'] }}</b>
                    <div class="gw-types">{{ implode(' · ', $d['types']) ?: '—' }}</div>
                </div>
                @if($d['ready'])
                    <span class="pill g"><i class="fa-solid fa-circle-check"></i> {{ __('Prête') }}</span>
                @else
                    <span class="pill a"><i class="fa-solid fa-circle-half-stroke"></i> {{ __('Incomplète') }}</span>
                @endif
            </div>

            <div class="gw-card-body">
                @foreach($spec['credentials'] as $key)
                    @php $src = $d['sources']['credentials.'.$key] ?? 'empty'; @endphp
                    <label class="gw-field">
                        <span class="gw-flabel">
                            {{ \Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields::label($key) }}
                            @if($src === 'db')<i class="gw-src ok" title="{{ __('Saisi ici') }}">{{ __('enregistré') }}</i>
                            @elseif($src === 'env')<i class="gw-src env" title="{{ __('Défini dans le .env du serveur') }}">.env</i>
                            @else<i class="gw-src miss">{{ __('absent') }}</i>@endif
                        </span>
                        <input class="inp" type="password" autocomplete="new-password" name="credentials[{{ $key }}]"
                               placeholder="{{ $src === 'empty' ? __('Coller la valeur…') : '••••••••  '.__('(laisser vide pour conserver)') }}">
                    </label>
                @endforeach

                @foreach($spec['extras'] as $key)
                    @php $src = $d['sources'][$key] ?? 'empty'; @endphp
                    <label class="gw-field">
                        <span class="gw-flabel">
                            {{ \Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields::label($key) }}
                            @if($src === 'db')<i class="gw-src ok">{{ __('enregistré') }}</i>
                            @elseif($src === 'env')<i class="gw-src env">.env</i>
                            @else<i class="gw-src miss">{{ __('absent') }}</i>@endif
                        </span>
                        <input class="inp" type="password" autocomplete="new-password" name="{{ $key }}"
                               placeholder="{{ $src === 'empty' ? __('Coller la valeur…') : '••••••••  '.__('(laisser vide pour conserver)') }}">
                    </label>
                @endforeach

                @if($spec['has_mode'])
                    <label class="gw-field">
                        <span class="gw-flabel">{{ __('Environnement') }}</span>
                        <select class="sel" name="mode">
                            @foreach(\Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields::MODES as $m)
                                <option value="{{ $m }}" @selected(($d['mode'] ?? 'sandbox') === $m)>
                                    {{ $m === 'live' ? __('Production (live)') : __('Test (sandbox)') }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            <div class="gw-card-foot">
                <button class="btn btn-p btn-sm"><i class="fa-solid fa-lock"></i> {{ __('Enregistrer') }}</button>
                <label class="gw-clear" title="{{ __('Supprimer les identifiants enregistrés ici') }}">
                    <input type="checkbox" name="clear" value="1"> {{ __('Effacer') }}
                </label>
            </div>
        </form>
    @endforeach
</div>

{{-- ============ 2. ACTIVATION, FRAIS, QUI ENCAISSE ============ --}}
<h3 class="gw-h" style="margin-top:30px">{{ __('2. Activation, frais et encaissement') }}</h3>

<div class="gw-warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>
        <b>{{ __('À lire avant de passer une passerelle en « TAGTOA encaisse »') }}</b>
        <p>{{ __('L\'argent des marchands arriverait alors sur VOS comptes, à charge pour vous de le leur reverser : c\'est le métier d\'agrégateur. PayPal l\'interdit hors contrat Commerce Platform, Stripe exige Stripe Connect, MonCash demande un accord avec Digicel. Sans ces accords, le compte peut être gelé — avec les fonds des marchands dessus.') }}</p>
        <p style="margin-top:6px">{{ __('En mode « le marchand encaisse », l\'argent va directement chez lui : aucun risque réglementaire.') }}</p>
    </div>
</div>

<form method="POST" action="{{ route('tagtoa.superadmin.gateways.update') }}">
    @csrf @method('PUT')

    @foreach(['auto' => __('Passerelles automatiques (API)'), 'manual' => __('Passerelles manuelles (preuve)')] as $group => $groupLabel)
        <div class="card" style="margin-bottom:16px">
            <div class="h-row"><h2>{{ $groupLabel }}</h2></div>
            @if($group === 'manual')
                <p style="color:var(--muted);font-size:13px;margin-top:-8px">
                    {{ __('Ces méthodes n\'encaissent jamais via TAGTOA : le marchand saisit ses propres coordonnées et approuve les preuves. Les frais ne s\'y appliquent pas.') }}
                </p>
            @endif

            <div style="overflow-x:auto">
            <table style="width:100%;min-width:660px">
                <thead>
                    <tr>
                        <th>{{ __('Passerelle') }}</th>
                        <th>{{ __('Qui encaisse') }}</th>
                        <th style="text-align:right">{{ __('Frais %') }}</th>
                        <th style="text-align:right">{{ __('Frais fixe') }}</th>
                        <th style="text-align:center">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($catalog[$group] as $type => $meta)
                    @php $isAuto = $meta['mode'] === \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO; @endphp
                    <tr>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:9px">
                                <span class="gw-dot" style="background:{{ $meta['color'] }}"><i class="{{ $meta['icon'] }}"></i></span>
                                <b>{{ $meta['label'] }}</b>
                                @if($isAuto && ($credentials[$type] ?? false))
                                    <span class="pill g" style="font-size:10.5px">{{ __('prête') }}</span>
                                @endif
                            </span>
                        </td>
                        <td>
                            @if($isAuto)
                                <select class="sel" name="gateways[{{ $type }}][credential_mode]" style="max-width:180px">
                                    <option value="{{ \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_MERCHANT }}" @selected($meta['credential_mode'] === \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_MERCHANT)>{{ __('Le marchand') }}</option>
                                    <option value="{{ \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_PLATFORM }}" @selected($meta['credential_mode'] === \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_PLATFORM)>{{ __('TAGTOA (agrégateur)') }}</option>
                                </select>
                            @else
                                <span style="color:var(--muted);font-size:13px">{{ __('Le marchand') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            <input class="inp" type="number" step="0.01" min="0" max="100" style="max-width:90px;text-align:right"
                                   name="gateways[{{ $type }}][fee_percent]" value="{{ $meta['fee_percent'] }}" @disabled(! $isAuto)>
                        </td>
                        <td style="text-align:right">
                            <input class="inp" type="number" step="0.01" min="0" style="max-width:100px;text-align:right"
                                   name="gateways[{{ $type }}][fee_fixed]" value="{{ $meta['fee_fixed'] }}" @disabled(! $isAuto)>
                        </td>
                        <td style="text-align:center">
                            <label class="switch" style="justify-content:center;margin:0">
                                <input type="checkbox" name="gateways[{{ $type }}][is_enabled]" value="1" @checked($meta['is_enabled'])>
                            </label>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endforeach

    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('Les frais sont enregistrés et prêts, mais ne seront prélevés que lorsque les passerelles automatiques encaisseront réellement.') }}
    </p>
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer les réglages') }}</button>
</form>

<style>
    .gw-hero{display:flex;align-items:center;gap:20px;flex-wrap:wrap;background:linear-gradient(135deg,#0d140c 0%,#14361f 60%,#1D9E75 100%);
             color:#fff;border-radius:20px;padding:24px 26px;margin-bottom:18px}
    .gw-hero>div:first-child{flex:1;min-width:220px}
    .gw-kicker{font:600 10.5px var(--fh);letter-spacing:.16em;text-transform:uppercase;opacity:.7}
    .gw-hero h2{font-family:var(--fh);font-size:24px;margin:6px 0 4px}
    .gw-hero p{font-size:14px;opacity:.85}
    .gw-score{text-align:right}
    .gw-score .n{font-family:var(--fh);font-weight:700;font-size:38px;line-height:1;display:block}
    .gw-score .n em{font-style:normal;opacity:.55;font-size:20px}
    .gw-score .l{font-size:12.5px;opacity:.8}
    .gw-h{font-family:var(--fh);font-size:17px;margin:0 0 4px}
    .gw-sub{color:var(--muted);font-size:13.5px;margin-bottom:14px}
    .gw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}
    .gw-card{background:var(--surface);border:1px solid var(--bd);border-radius:16px;padding:16px;display:flex;flex-direction:column;
             transition:box-shadow .18s,border-color .18s}
    .gw-card:hover{box-shadow:0 6px 22px rgba(0,0,0,.07)}
    .gw-card.is-ready{border-color:rgba(44,184,9,.45)}
    .gw-card-head{display:flex;align-items:center;gap:11px;padding-bottom:12px;border-bottom:1px solid var(--bd)}
    .gw-logo{width:38px;height:38px;border-radius:11px;background:var(--blk);color:#fff;display:flex;align-items:center;
             justify-content:center;font:700 13px var(--fh);flex:0 0 38px}
    .gw-card.is-ready .gw-logo{background:var(--blue)}
    .gw-card-head b{font-family:var(--fh);font-size:15px;display:block}
    .gw-types{font-size:11.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .gw-card-body{flex:1;padding-top:4px}
    .gw-field{display:block;margin-top:11px}
    .gw-flabel{display:flex;align-items:center;gap:7px;font:600 12px var(--fh);color:#555;margin-bottom:5px}
    .gw-src{font-style:normal;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.03em}
    .gw-src.ok{background:#eafaf3;color:#0e5f44}
    .gw-src.env{background:#eef2ff;color:#3344aa}
    .gw-src.miss{background:#f3f3f3;color:#999}
    .gw-card-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid var(--bd)}
    .gw-clear{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--red);cursor:pointer}
    .gw-warn{display:flex;gap:12px;background:#fdecea;border:1px solid rgba(224,71,62,.35);border-left:4px solid var(--red);
             border-radius:14px;padding:15px 17px;margin-bottom:16px}
    .gw-warn i{color:var(--red);font-size:17px;margin-top:2px}
    .gw-warn b{font-family:var(--fh);font-size:14px;display:block;margin-bottom:4px}
    .gw-warn p{font-size:13px;color:#7a2b25;line-height:1.55}
    .gw-dot{width:26px;height:26px;border-radius:7px;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px}
</style>
@endsection
