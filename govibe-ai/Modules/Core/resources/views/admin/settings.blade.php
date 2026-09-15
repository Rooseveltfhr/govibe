@php
    $languages = ['ht' => 'Kreyòl', 'fr' => 'Français', 'en' => 'English', 'es' => 'Español'];
@endphp

<x-core::layouts.admin :title="__('Configuration')">

    <h1>{{ __('Configuration') }}</h1>
    <p class="lead">{{ __("Les réglages de la plateforme, et les clés qui allument les fournisseurs.") }}</p>

    @error('api_key') <div class="note">{{ $message }}</div> @enderror

    <h2>{{ __('Plateforme') }}</h2>
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        <fieldset>
            <legend>{{ __('Réglages') }}</legend>
            <div class="grid2">
                <div>
                    <label for="platform_name">{{ __('Nom de la plateforme') }}</label>
                    <input type="text" id="platform_name" name="platform_name" maxlength="60"
                           value="{{ old('platform_name', $settings->get('platform_name')) }}">
                </div>
                <div>
                    <label for="support_whatsapp">{{ __('WhatsApp du support') }}</label>
                    <input type="text" id="support_whatsapp" name="support_whatsapp" maxlength="40"
                           value="{{ old('support_whatsapp', $settings->get('support_whatsapp')) }}">
                </div>
                <div>
                    <label for="default_language">{{ __('Langue par défaut') }}</label>
                    <select id="default_language" name="default_language">
                        @foreach ($languages as $code => $label)
                            <option value="{{ $code }}" @selected($settings->get('default_language') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="hero_headline">{{ __("Titre de la page d'accueil") }}
                        <span class="hint">{{ __('Vide = le titre livré avec le site.') }}</span>
                    </label>
                    <input type="text" id="hero_headline" name="hero_headline" maxlength="160"
                           value="{{ old('hero_headline', $settings->get('hero_headline')) }}">
                </div>
            </div>
        </fieldset>
        <div class="row">
            <button type="submit" class="btn btn-primary">{{ __('Enregistrer') }}</button>
        </div>
    </form>

    <h2>{{ __('Fournisseurs') }}</h2>
    <p class="lead">
        {{ __("Une clé posée ici est chiffrée en base et n'est jamais réaffichée. Une clé posée dans le .env du serveur est prioritaire et ne peut pas être remplacée depuis le web.") }}
    </p>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>{{ __('Fournisseur') }}</th>
                <th>{{ __('Sait faire') }}</th>
                <th>{{ __('Clé') }}</th>
                <th>{{ __('Poser une clé') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($providers as $provider)
                <tr>
                    <td>
                        {{ $provider['name'] }}<br>
                        <span class="empty mono">{{ $provider['key'] }}</span>
                    </td>
                    <td class="empty">{{ implode(', ', $provider['capabilities']) }}</td>
                    <td>
                        @if ($provider['configured'])
                            <span class="pill on">{{ __('Posée') }}</span>
                            <span class="empty">{{ $provider['source'] === 'env' ? __('serveur (.env)') : __('base') }}</span>
                        @else
                            <span class="pill off">{{ __('Absente') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($provider['locked'])
                            <span class="empty">{{ __('Fixée sur le serveur.') }}</span>
                        @else
                            <form method="POST" action="{{ route('admin.settings.key') }}">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $provider['key'] }}">
                                <div class="row">
                                    <input type="password" name="api_key" autocomplete="off" style="max-width:220px"
                                           placeholder="{{ $provider['source'] === 'db' ? __('Remplacer…') : __('Coller la clé') }}">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Poser') }}</button>
                                </div>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

</x-core::layouts.admin>
