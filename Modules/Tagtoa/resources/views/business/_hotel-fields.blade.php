{{-- TAGTOA HÔTEL (H1) — champs propres à l'hôtel. Affichés seulement si le type choisi est « hôtel » ;
     tout est facultatif. Le serveur revalide (HotelCatalog::rules) et ignore ces champs pour tout
     autre type : ce bloc n'est qu'un confort d'affichage. Variables : $hotel, $facilities, $business. --}}
@php
    $typeChoisi = old('type', $business->type);
    $facOn  = old('hotel.facilities', $hotel->facilities ?? []);
    $langOn = old('hotel.languages', $hotel->languages ?? []);
    $h24    = (bool) old('hotel.reception_24h', $hotel->reception_24h ?? true);
@endphp
<div class="card" id="hotel-fields" style="{{ $typeChoisi === 'hotel' ? '' : 'display:none' }}">
    <h3 style="margin:0 0 4px"><i class="fa-solid fa-hotel"></i> {{ __('Votre hôtel') }}</h3>
    <p style="color:var(--muted);font-size:13px;margin:0 0 8px">{{ __('Tout est facultatif : vous pourrez compléter plus tard.') }}</p>

    <label class="lbl">{{ __('Description') }}</label>
    <textarea class="inp" name="hotel[description]" rows="3" maxlength="1000"
              placeholder="{{ __('Présentez votre hôtel en quelques phrases') }}">{{ old('hotel.description', $hotel->description) }}</textarea>

    <div class="row">
        <div>
            <label class="lbl">{{ __('Nombre de chambres') }}</label>
            <input class="inp" type="number" name="hotel[rooms_count]" min="1" max="2000" inputmode="numeric"
                   value="{{ old('hotel.rooms_count', $hotel->rooms_count) }}">
        </div>
        <div>
            <label class="lbl">{{ __('Image de couverture') }}</label>
            <input class="inp" type="file" name="hotel_cover" accept="image/*">
            @if($hotel->cover_url)<img src="{{ $hotel->cover_url }}" alt="" style="margin-top:6px;max-height:60px;border-radius:8px">@endif
        </div>
    </div>

    <label class="lbl">{{ __('Réception') }}</label>
    <div class="row">
        <div><input class="inp" name="hotel[reception_phone]" maxlength="40" inputmode="tel"
                    placeholder="{{ __('Téléphone de la réception') }}" value="{{ old('hotel.reception_phone', $hotel->reception_phone) }}"></div>
        <div><input class="inp" type="email" name="hotel[reception_email]" maxlength="120"
                    placeholder="{{ __('E-mail de la réception') }}" value="{{ old('hotel.reception_email', $hotel->reception_email) }}"></div>
    </div>
    <div class="chipwrap" style="margin-top:8px">
        <label class="chip">
            <input type="hidden" name="hotel[reception_24h]" value="0">
            <input type="checkbox" name="hotel[reception_24h]" value="1" id="h24" @checked($h24)>
            <span>{{ __('Réception ouverte 24h/24') }}</span>
        </label>
    </div>
    <input class="inp" name="hotel[reception_hours]" id="hhours" maxlength="80" style="margin-top:6px;{{ $h24 ? 'display:none' : '' }}"
           placeholder="{{ __('Horaires (ex. 7h – 22h)') }}" value="{{ old('hotel.reception_hours', $hotel->reception_hours) }}">

    <label class="lbl">{{ __('Installations') }}</label>
    <div class="chipwrap">
        @foreach($facilities as $cle => $libelle)
            <label class="chip">
                <input type="checkbox" name="hotel[facilities][]" value="{{ $cle }}" @checked(in_array($cle, (array) $facOn, true))>
                <span>{{ __($libelle) }}</span>
            </label>
        @endforeach
    </div>

    <label class="lbl">{{ __('Langues parlées / proposées aux clients') }}</label>
    <div class="chipwrap">
        @foreach(\Modules\Tagtoa\App\Support\Locale::all() as $code => $meta)
            <label class="chip">
                <input type="checkbox" name="hotel[languages][]" value="{{ $code }}" @checked(in_array($code, (array) $langOn, true))>
                <span>{{ $meta['label'] ?? $code }}</span>
            </label>
        @endforeach
    </div>

    <div class="chipwrap" style="margin-top:10px">
        <label class="chip">
            <input type="hidden" name="hotel[room_service_enabled]" value="0">
            <input type="checkbox" name="hotel[room_service_enabled]" value="1"
                   @checked((bool) old('hotel.room_service_enabled', $hotel->room_service_enabled))>
            <span>{{ __('Service en chambre disponible') }}</span>
        </label>
    </div>

    <label class="lbl">{{ __('Informations pour les clients') }}</label>
    <textarea class="inp" name="hotel[guest_info]" rows="3" maxlength="1500"
              placeholder="{{ __('Wi-Fi, heure du petit-déjeuner, numéros utiles…') }}">{{ old('hotel.guest_info', $hotel->guest_info) }}</textarea>
</div>
