@php
    $chosen = old('sector', $selected);
    $chosenChannels = old('channels', ['whatsapp']);
    $mode = old('mode', 'expert');
@endphp

<x-agents::layouts.app :title="__('Commander un agent')">

    <div class="page-head">
        <h1>{{ __('Commander un agent') }}</h1>
        <p class="lead">
            {{ __("Vous n'avez pas à savoir configurer quoi que ce soit : dites-nous quel métier et sur quel numéro, un expert GOVIBE monte l'agent et vous le remet prêt.") }}
        </p>
    </div>

    <form method="POST" action="{{ route('orders.store') }}">
        @csrf

        <fieldset>
            <legend>{{ __('Le modèle') }}</legend>

            <label for="sector">{{ __('Quel modèle ?') }}</label>
            <select id="sector" name="sector" required>
                <option value="">{{ __('Choisissez…') }}</option>
                @foreach ($templates as $t)
                    <option value="{{ $t->sector }}" @selected($chosen === $t->sector)>
                        {{ $t->label }} — {{ $t->isChatbot() ? __('Chatbot') : __('Agent') }}
                    </option>
                @endforeach
            </select>
            @error('sector') <div class="err">{{ $message }}</div> @enderror

            <label>
                {{ __('Qui le met en place ?') }}
                <span class="hint">{{ __("Les deux mènent au même agent — c'est une question de temps, pas de qualité.") }}</span>
            </label>
            <div class="choices">
                <label class="choice">
                    <input type="radio" name="mode" value="expert" @checked($mode === 'expert')>
                    <span>
                        <strong>{{ __('Un expert GOVIBE le monte pour moi') }}</strong>
                        <small>{{ __("Vous envoyez votre menu, vos horaires, vos tarifs ; nous faisons le reste.") }}</small>
                    </span>
                </label>
                <label class="choice">
                    <input type="radio" name="mode" value="self" @checked($mode === 'self')>
                    <span>
                        <strong>{{ __('Je le crée moi-même') }}</strong>
                        <small>{{ __("Vous remplissez la fiche ; nous branchons WhatsApp et le site.") }}</small>
                    </span>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>{{ __('Votre entreprise') }}</legend>

            <label for="business_name">{{ __("Nom de l'entreprise") }}</label>
            <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}" required maxlength="160">
            @error('business_name') <div class="err">{{ $message }}</div> @enderror

            <label for="contact_name">{{ __('Votre nom') }}</label>
            <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" maxlength="120">

            <label for="whatsapp">
                {{ __('WhatsApp') }}
                <span class="hint">{{ __("C'est là que nous vous répondrons.") }}</span>
            </label>
            <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" required maxlength="40"
                   placeholder="+509 0000 0000">
            @error('whatsapp') <div class="err">{{ $message }}</div> @enderror

            <label for="email">{{ __('E-mail (facultatif)') }}</label>
            <input type="text" id="email" name="email" value="{{ old('email') }}" maxlength="160">
            @error('email') <div class="err">{{ $message }}</div> @enderror
        </fieldset>

        <fieldset>
            <legend>{{ __('Où doit-il répondre ?') }}</legend>

            <div class="choices">
                @php
                    $channelLabels = [
                        'whatsapp' => __('WhatsApp'),
                        'website' => __('Sur mon site web'),
                        'phone' => __('Au téléphone, à la voix'),
                    ];
                @endphp
                @foreach ($channelLabels as $value => $label)
                    <label class="choice">
                        <input type="checkbox" name="channels[]" value="{{ $value }}"
                               @checked(in_array($value, (array) $chosenChannels, true))>
                        <span><strong>{{ $label }}</strong></span>
                    </label>
                @endforeach
            </div>

            <label for="notes">{{ __('Ce que vous voulez lui faire faire') }}</label>
            <textarea id="notes" name="notes" maxlength="2000"
                      placeholder="{{ __('Exemple : prendre les commandes le soir, donner les prix, demander l’adresse avec un repère.') }}">{{ old('notes') }}</textarea>
        </fieldset>

        <div class="row">
            <button type="submit" class="btn btn-primary">{{ __('Envoyer la demande') }}</button>
            <a class="btn" href="{{ route('home') }}">{{ __('Annuler') }}</a>
        </div>
    </form>

    <p class="back"><a href="{{ route('home') }}">{{ __('Accueil') }}</a></p>

</x-agents::layouts.app>
