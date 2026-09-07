@php
    $mine = [];
    $library = [];

    foreach ($voices as $voice) {
        if ($voice->isMine()) {
            $mine[] = $voice;
        } else {
            $library[] = $voice;
        }
    }
@endphp

<x-agents::layouts.app :title="__('Voix')">

    <div class="page-head">
        <h1>{{ __('Voix') }} — {{ $agent->name }}</h1>
        <p class="lead">
            {{ __("Choisissez la voix de cet agent, ou enregistrez la vôtre. Une voix clonée fait sonner l'agent comme votre entreprise, pas comme tout le monde.") }}
        </p>
    </div>

    @error('voice_id') <div class="note">{{ $message }}</div> @enderror
    @error('samples') <div class="note">{{ $message }}</div> @enderror

    @unless ($available)
        <div class="note">
            <strong>{{ __("Aucune clé de voix n'est configurée sur ce serveur.") }}</strong>
            {{ __("La bibliothèque est vide tant qu'une clé ElevenLabs n'est pas ajoutée : l'agent répondra par écrit.") }}
        </div>
    @endunless

    <h2>{{ __('Vos voix') }}</h2>
    @if ($mine === [])
        <p class="empty">{{ __("Vous n'avez encore enregistré aucune voix.") }}</p>
    @else
        <form method="POST" action="{{ route('agents.voice.update', $agent) }}">
            @csrf
            <div class="choices">
                @foreach ($mine as $voice)
                    <label class="choice">
                        <input type="radio" name="voice_id" value="{{ $voice->id }}" @checked($current === $voice->id)>
                        <span>
                            <strong>{{ $voice->name }}</strong>
                            <small>{{ $voice->description ?: __('Voix enregistrée') }}</small>
                            @if ($voice->previewUrl)
                                <audio controls preload="none" src="{{ $voice->previewUrl }}"></audio>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="row" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">{{ __('Utiliser cette voix') }}</button>
            </div>
        </form>
    @endif

    <h2>{{ __('Bibliothèque') }}</h2>
    @if ($library === [])
        <p class="empty">{{ __('Aucune voix disponible.') }}</p>
    @else
        <form method="POST" action="{{ route('agents.voice.update', $agent) }}">
            @csrf
            <div class="choices">
                <label class="choice">
                    <input type="radio" name="voice_id" value="" @checked($current === null)>
                    <span>
                        <strong>{{ __('Voix par défaut') }}</strong>
                        <small>{{ __("Celle du serveur, tant que vous n'en choisissez pas une autre.") }}</small>
                    </span>
                </label>
                @foreach ($library as $voice)
                    <label class="choice">
                        <input type="radio" name="voice_id" value="{{ $voice->id }}" @checked($current === $voice->id)>
                        <span>
                            <strong>{{ $voice->name }}</strong>
                            <small>{{ $voice->description ?: implode(' · ', $voice->labels) }}</small>
                            @if ($voice->previewUrl)
                                <audio controls preload="none" src="{{ $voice->previewUrl }}"></audio>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="row" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">{{ __('Utiliser cette voix') }}</button>
            </div>
        </form>
    @endif

    <h2>{{ __('Enregistrer votre voix') }}</h2>
    <form method="POST" action="{{ route('agents.voice.store', $agent) }}" enctype="multipart/form-data">
        @csrf
        <fieldset>
            <legend>{{ __('Nouvelle voix') }}</legend>

            <label for="voice-name">{{ __('Nom de la voix') }}</label>
            <input type="text" id="voice-name" name="name" maxlength="120" required value="{{ old('name') }}"
                   placeholder="{{ $agent->name }}">
            @error('name') <div class="err">{{ $message }}</div> @enderror

            <label for="voice-desc">{{ __('Description (facultatif)') }}</label>
            <input type="text" id="voice-desc" name="description" maxlength="500" value="{{ old('description') }}">

            <label for="samples">
                {{ __('Échantillons audio') }}
                <span class="hint">{{ __("Une à cinq minutes de parole claire, sans bruit de fond. Les fichiers ne sont pas conservés chez nous : ils partent au fournisseur puis sont effacés.") }}</span>
            </label>
            <input type="file" id="samples" name="samples[]" accept="audio/*" multiple required>
        </fieldset>

        <div class="row">
            <button type="submit" class="btn btn-primary" @disabled(! $available)>{{ __('Enregistrer la voix') }}</button>
            <a class="btn" href="{{ route('agents.show', $agent) }}">{{ __("Retour à l'agent") }}</a>
        </div>
    </form>

    <p class="back"><a href="{{ route('agents.show', $agent) }}">{{ __("Retour à l'agent") }}</a></p>

</x-agents::layouts.app>
