@extends('tagtoa::layouts.dashboard')
@section('title', __('Passerelles de paiement'))
@section('page', __('Super-admin — passerelles de paiement'))

@section('content')
{{-- Réservé au fondateur. Aucun identifiant n'est affiché : seulement leur présence. --}}
<div class="h-row" style="gap:8px;margin-bottom:16px">
    <a href="{{ route('tagtoa.superadmin.index') }}" class="btn btn-o btn-sm"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <a href="{{ route('tagtoa.superadmin.status') }}" class="btn btn-o btn-sm"><i class="fa-solid fa-server"></i> {{ __('État système') }}</a>
</div>

<div class="card" style="margin-bottom:16px;border-left:4px solid #E0473E">
    <b style="font-family:var(--fh,sans-serif)">{{ __('À lire avant de passer une passerelle en « plateforme »') }}</b>
    <p style="color:var(--muted,#555);font-size:13.5px;margin-top:6px;line-height:1.6">
        {{ __('En mode « plateforme », l\'argent des marchands arrive sur VOS comptes et vous devez ensuite le leur reverser : c\'est le métier d\'agrégateur de paiement. PayPal l\'interdit hors contrat Commerce Platform, Stripe exige Stripe Connect, et MonCash demande un accord agrégateur avec Digicel. Sans ces accords, le risque concret est le gel du compte — avec les fonds des marchands dessus.') }}
        <br><br>
        {{ __('En mode « marchand », le marchand branche ses propres identifiants : l\'argent va directement chez lui, TAGTOA ne touche jamais les fonds, et il n\'y a aucun risque réglementaire.') }}
    </p>
</div>

<form method="POST" action="{{ route('tagtoa.superadmin.gateways.update') }}">
    @csrf @method('PUT')

    @foreach(['auto' => __('Passerelles automatiques (API)'), 'manual' => __('Passerelles manuelles (preuve)')] as $group => $groupLabel)
        <div class="card" style="margin-bottom:16px">
            <div class="h-row"><h2>{{ $groupLabel }}</h2></div>

            @if($group === 'manual')
                <p style="color:var(--muted,#888);font-size:13px;margin-top:-8px">
                    {{ __('Ces méthodes n\'encaissent jamais via TAGTOA : le marchand saisit ses propres coordonnées et approuve les preuves. Les frais ci-dessous ne s\'appliquent donc pas.') }}
                </p>
            @endif

            <div style="overflow-x:auto">
            <table style="width:100%;min-width:640px">
                <thead>
                    <tr>
                        <th style="text-align:left">{{ __('Passerelle') }}</th>
                        <th style="text-align:left">{{ __('Identifiants plateforme') }}</th>
                        <th style="text-align:left">{{ __('Qui encaisse') }}</th>
                        <th style="text-align:right">{{ __('Frais %') }}</th>
                        <th style="text-align:right">{{ __('Frais fixe') }}</th>
                        <th style="text-align:center">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($catalog[$group] as $type => $meta)
                    <tr>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:9px">
                                <span style="width:26px;height:26px;border-radius:7px;background:{{ $meta['color'] }};color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px"><i class="{{ $meta['icon'] }}"></i></span>
                                <b>{{ $meta['label'] }}</b>
                            </span>
                        </td>
                        <td>
                            @if($meta['mode'] === \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO)
                                @if($credentials[$type] ?? false)
                                    <span style="color:#1b5e12;font-weight:600;font-size:13px">{{ __('Configurés') }}</span>
                                @else
                                    <span style="color:#9a2820;font-weight:600;font-size:13px">{{ __('Manquants') }}</span>
                                @endif
                            @else
                                <span style="color:var(--muted,#888);font-size:13px">—</span>
                            @endif
                        </td>
                        <td>
                            @if($meta['mode'] === \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO)
                                <select class="sel" name="gateways[{{ $type }}][credential_mode]" style="max-width:170px">
                                    <option value="{{ \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_MERCHANT }}" @selected($meta['credential_mode'] === \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_MERCHANT)>{{ __('Le marchand') }}</option>
                                    <option value="{{ \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_PLATFORM }}" @selected($meta['credential_mode'] === \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::MODE_PLATFORM)>{{ __('TAGTOA (agrégateur)') }}</option>
                                </select>
                            @else
                                <span style="color:var(--muted,#888);font-size:13px">{{ __('Le marchand') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            <input class="inp" type="number" step="0.01" min="0" max="100" style="max-width:90px;text-align:right"
                                   name="gateways[{{ $type }}][fee_percent]" value="{{ $meta['fee_percent'] }}"
                                   @disabled($meta['mode'] !== \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO)>
                        </td>
                        <td style="text-align:right">
                            <input class="inp" type="number" step="0.01" min="0" style="max-width:100px;text-align:right"
                                   name="gateways[{{ $type }}][fee_fixed]" value="{{ $meta['fee_fixed'] }}"
                                   @disabled($meta['mode'] !== \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO)>
                        </td>
                        <td style="text-align:center">
                            <input type="checkbox" name="gateways[{{ $type }}][is_enabled]" value="1" @checked($meta['is_enabled'])>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endforeach

    <p style="color:var(--muted,#888);font-size:12.5px;margin-bottom:12px">
        {{ __('Note : les frais sont enregistrés et prêts, mais ne seront prélevés que lorsque les passerelles automatiques encaisseront réellement (identifiants configurés).') }}
    </p>

    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>
@endsection
