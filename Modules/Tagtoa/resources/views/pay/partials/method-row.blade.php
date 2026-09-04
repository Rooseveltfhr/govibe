{{-- TAGTOA Pay — une ligne de « Mes moyens de paiement ».
     Variables : $type (clé), $meta (métadonnées effectives), $m (PaymentMethod|null).

     Formulaire indexé PAR CLÉ DE PASSERELLE (methods[moncash][…]) : le serveur
     peut donc rejeter toute clé inconnue avant d'écrire quoi que ce soit. --}}
@php
    $on       = $m ? (bool) $m->is_active : false;
    $isAuto   = $meta['mode'] === \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO;
    $apiReady = ! empty($meta['online_ready']);
@endphp
<div class="gwrow">
    <label class="gwhead">
        <span class="gwicon" style="background:{{ $meta['color'] }}"><i class="{{ $meta['icon'] }}"></i></span>
        <span class="gwname">{{ $meta['label'] }}</span>

        @if($isAuto)
            @if($apiReady)
                <span class="gwtag ok">{{ __('Prêt') }}</span>
            @else
                <span class="gwtag off">{{ __('Pas encore disponible') }}</span>
            @endif
        @endif

        <span class="sw">
            <input type="checkbox" name="methods[{{ $type }}][enabled]" value="1" @checked($on) onchange="gwToggle(this)">
            <span></span>
        </span>
    </label>

    <div class="gwbody" style="{{ $on ? '' : 'display:none' }}">
        @if($isAuto)
            @if($apiReady)
                <p style="color:var(--muted);font-size:13px;margin-top:12px">
                    <i class="fa-solid fa-bolt" style="color:#2cb809"></i>
                    {{ __('Encaissement automatique. Rien à saisir : TAGTOA gère la connexion au fournisseur.') }}
                </p>
            @else
                <p style="color:#7a5200;font-size:13px;margin-top:12px">
                    <i class="fa-solid fa-clock"></i>
                    {{ __('Cette passerelle n\'est pas encore activée par TAGTOA. Vous pouvez la préparer : elle fonctionnera sur preuve manuelle en attendant, et basculera toute seule en automatique dès qu\'elle sera disponible.') }}
                </p>
            @endif
            <label class="lbl">{{ __('Libellé affiché au client') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
            <input name="methods[{{ $type }}][label]" class="inp" maxlength="120"
                   value="{{ old('methods.'.$type.'.label', $m->label ?? '') }}" placeholder="{{ $meta['label'] }}">
        @endif

        @if(! $isAuto || ! $apiReady)
            <div class="row" style="margin-top:12px">
                <div>
                    <label class="lbl">{{ __('Nom du compte') }}</label>
                    <input name="methods[{{ $type }}][account_holder]" class="inp" maxlength="160"
                           value="{{ old('methods.'.$type.'.account_holder', $m->account_holder ?? '') }}"
                           placeholder="{{ __('Nom du titulaire') }}">
                </div>
                <div>
                    <label class="lbl">{{ __('Numéro de compte') }}</label>
                    <input name="methods[{{ $type }}][account_number]" class="inp" maxlength="190"
                           value="{{ old('methods.'.$type.'.account_number', $m->account_number ?? '') }}"
                           placeholder="{{ __('N° de compte / téléphone / adresse wallet') }}">
                </div>
            </div>

            <div class="row" style="margin-top:8px">
                <div>
                    <label class="lbl">{{ __('Institution') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
                    <input name="methods[{{ $type }}][institution]" class="inp" maxlength="160"
                           value="{{ old('methods.'.$type.'.institution', $m->institution ?? '') }}"
                           placeholder="{{ __('Banque / opérateur') }}">
                </div>
                @if(! $isAuto)
                    <div>
                        <label class="lbl">{{ __('Libellé affiché') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
                        <input name="methods[{{ $type }}][label]" class="inp" maxlength="120"
                               value="{{ old('methods.'.$type.'.label', $m->label ?? '') }}" placeholder="{{ $meta['label'] }}">
                    </div>
                @endif
            </div>

            <label class="lbl" style="margin-top:8px">{{ __('Instructions pour le client') }}</label>
            <textarea name="methods[{{ $type }}][instructions]" class="inp" rows="2" maxlength="1000"
                      placeholder="{{ __('Ex. Envoyez le montant exact puis téléversez la capture.') }}">{{ old('methods.'.$type.'.instructions', $m->instructions ?? '') }}</textarea>

            <div class="row" style="margin-top:8px;align-items:flex-end">
                <div>
                    <label class="lbl">{{ __('QR Code') }} <span style="font-weight:400;color:var(--muted)">({{ __('image, max 3 Mo') }})</span></label>
                    <input type="file" name="methods[{{ $type }}][qr]" accept="image/*" class="inp">
                </div>
                <div>
                    <label class="lbl">{{ __('Logo') }} <span style="font-weight:400;color:var(--muted)">({{ __('image, max 1 Mo') }})</span></label>
                    <input type="file" name="methods[{{ $type }}][logo]" accept="image/*" class="inp">
                </div>
                <div style="flex:0;display:flex;gap:8px;align-items:center;padding-bottom:4px">
                    @if($m && $m->qr_url)<img src="{{ $m->qr_url }}" alt="QR" style="height:44px;border-radius:8px">@endif
                    @if($m && $m->logo_url)<img src="{{ $m->logo_url }}" alt="Logo" style="height:44px;border-radius:8px">@endif
                </div>
            </div>
        @endif
    </div>
</div>
