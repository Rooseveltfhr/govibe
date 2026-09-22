<x-core::layouts.admin :title="__('Paiement')">

    <h1>{{ __('Paiement') }}</h1>
    <p class="lead">
        {{ __("Comment les entreprises vous paient : MonCash, et les comptes bancaires pour un virement. Ces informations apparaissent sur le dossier d'une commande.") }}
    </p>

    <form method="POST" action="{{ route('admin.payments.update') }}">
        @csrf

        <fieldset>
            <legend>MonCash</legend>
            <label style="display:flex;gap:.5rem;align-items:center;margin-top:0">
                <input type="checkbox" name="moncash_enabled" value="1" style="width:auto"
                       @checked($settings->get('moncash_enabled'))>
                <span>{{ __('Proposer MonCash aux clients') }}</span>
            </label>

            <div class="grid2">
                <div>
                    <label for="moncash_client_id">{{ __('Identifiant client (Client ID)') }}</label>
                    <input type="text" id="moncash_client_id" name="moncash_client_id" maxlength="120"
                           value="{{ old('moncash_client_id', $settings->get('moncash_client_id')) }}">
                </div>
                <div>
                    <label for="moncash_mode">{{ __('Mode') }}</label>
                    <select id="moncash_mode" name="moncash_mode">
                        <option value="sandbox" @selected($settings->get('moncash_mode') === 'sandbox')>{{ __('Test (sandbox)') }}</option>
                        <option value="live" @selected($settings->get('moncash_mode') === 'live')>{{ __('Production (live)') }}</option>
                    </select>
                </div>
            </div>

            <label for="moncash_client_secret">{{ __('Clé secrète (Client Secret)') }}
                <span class="hint">
                    @if ($moncashConfigured)
                        {{ __('Une clé est déjà enregistrée. Laissez vide pour la garder.') }}
                    @else
                        {{ __("Aucune clé enregistrée pour l'instant.") }}
                    @endif
                </span>
            </label>
            <div class="row">
                <input type="password" id="moncash_client_secret" name="moncash_client_secret" autocomplete="off"
                       style="max-width:280px" placeholder="{{ $moncashConfigured ? __('Remplacer…') : __('Coller la clé') }}">
                <span class="pill {{ $moncashConfigured ? 'on' : 'off' }}">
                    {{ $moncashConfigured ? __('Posée') : __('Absente') }}
                </span>
            </div>
        </fieldset>

        <fieldset>
            <legend>{{ __('Virement bancaire') }}</legend>
            <p class="lead" style="margin-top:0">
                {{ __("Le compte à donner à un client qui préfère payer par virement, un par devise.") }}
            </p>
            <div class="grid2">
                <div>
                    <label for="bank_htg">{{ __('Compte en gourdes (HTG)') }}
                        <span class="hint">{{ __('Banque, numéro de compte, titulaire.') }}</span>
                    </label>
                    <textarea id="bank_htg" name="bank_htg" maxlength="600">{{ old('bank_htg', $settings->get('bank_htg')) }}</textarea>
                </div>
                <div>
                    <label for="bank_usd">{{ __('Compte en dollars (USD)') }}
                        <span class="hint">{{ __('Banque, numéro de compte, titulaire.') }}</span>
                    </label>
                    <textarea id="bank_usd" name="bank_usd" maxlength="600">{{ old('bank_usd', $settings->get('bank_usd')) }}</textarea>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>{{ __('Instructions pour le client') }}</legend>
            <label for="payment_instructions">
                <span class="hint">{{ __('Un texte court affiché sur le dossier de commande — par exemple les modalités acceptées.') }}</span>
            </label>
            <textarea id="payment_instructions" name="payment_instructions" maxlength="600">{{ old('payment_instructions', $settings->get('payment_instructions')) }}</textarea>
        </fieldset>

        <div class="row">
            <button type="submit" class="btn btn-primary">{{ __('Enregistrer') }}</button>
        </div>
    </form>

</x-core::layouts.admin>
