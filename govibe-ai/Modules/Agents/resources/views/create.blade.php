@php
    // Chan konesans yo depann de sektè a: yon restoran gen yon mni, yon
    // klinik gen sèvis. Nou mande sa ki itil pou sektè a sèlman.
    $fields = match ($descriptor->sector) {
        // L'agent ne cite que les moyens de paiement listés ici — sans ce champ
        // il répond qu'il n'est pas sûr, ce qui bloque une commande.
        'restaurant' => ['Menu' => 'menu', 'Horaires' => 'horaires', 'Adresse' => 'adresse', 'Livraison' => 'livraison', 'Paiement' => 'paiement'],
        'clinic' => ['Services' => 'services', 'Horaires' => 'horaires', 'Adresse' => 'adresse', 'Tarifs' => 'tarifs'],
        default => ['Programmes' => 'programmes', 'Horaires' => 'horaires', 'Adresse' => 'adresse', 'Frais' => 'frais'],
    };
@endphp

<x-agents::layouts.app :title="__('Créer un agent')">

    <div class="page-head">
        <h1>{{ __('Créer') }} — {{ $descriptor->label }}</h1>
        <p class="lead">{{ $descriptor->description }}</p>
    </div>

    <div class="note">
        {{ __("Ce que vous écrivez ici est tout ce que l'agent saura. S'il ne trouve pas la réponse, il le dira au lieu d'inventer.") }}
    </div>

    <form method="POST" action="{{ route('agents.store') }}">
        @csrf
        <input type="hidden" name="sector" value="{{ $descriptor->sector }}">

        <fieldset>
            <legend>{{ __('Entreprise') }}</legend>

            <label for="name">{{ __("Nom de l'entreprise") }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120">
            @error('name') <div class="err">{{ $message }}</div> @enderror

            <label for="handoff">
                {{ __('Contact humain') }}
                <span class="hint">{{ __("L'agent y renvoie le client dès qu'il n'est pas sûr.") }}</span>
            </label>
            <input type="text" id="handoff" name="handoff_to" value="{{ old('handoff_to') }}" maxlength="120">
        </fieldset>

        <fieldset>
            <legend>{{ __("Ce que l'agent saura") }}</legend>

            @foreach ($fields as $label => $key)
                <label for="k-{{ $key }}">{{ __($label) }}</label>
                <textarea id="k-{{ $key }}" name="knowledge[{{ $key }}]">{{ old('knowledge.'.$key) }}</textarea>
            @endforeach
        </fieldset>

        <div class="row">
            <button type="submit" class="btn btn-primary">{{ __('Créer') }}</button>
            <a class="btn" href="{{ route('agents.index') }}">{{ __('Annuler') }}</a>
        </div>
    </form>

    <h2>{{ __('Questions que cet agent sait traiter') }}</h2>
    <div class="list">
        @foreach ($questions as $q)
            <div class="item">{{ $q }}</div>
        @endforeach
    </div>

    <p class="back"><a href="{{ route('agents.index') }}">{{ __('Tous les modèles') }}</a></p>

</x-agents::layouts.app>
