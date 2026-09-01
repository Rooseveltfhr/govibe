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

    <h1>{{ __('Créer') }} — {{ $descriptor->label }}</h1>
    <p class="lead">{{ $descriptor->description }}</p>

    <div class="note">
        {{ __("Ce que vous écrivez ici est tout ce que l'agent saura. S'il ne trouve pas la réponse, il le dira au lieu d'inventer.") }}
    </div>

    <form method="POST" action="{{ route('agents.store') }}">
        @csrf
        <input type="hidden" name="sector" value="{{ $descriptor->sector }}">

        <label for="name">{{ __("Nom de l'entreprise") }}</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120">
        @error('name') <div class="err">{{ $message }}</div> @enderror

        @foreach ($fields as $label => $key)
            <label for="k-{{ $key }}">{{ __($label) }}</label>
            <textarea id="k-{{ $key }}" name="knowledge[{{ $key }}]">{{ old('knowledge.'.$key) }}</textarea>
        @endforeach

        <label for="handoff">{{ __("Contact humain (si l'agent n'est pas sûr)") }}</label>
        <input type="text" id="handoff" name="handoff_to" value="{{ old('handoff_to') }}" maxlength="120">

        <div class="row" style="margin-top:1.3rem">
            <button type="submit" class="btn btn-primary">{{ __('Créer') }}</button>
            <a class="btn" href="{{ route('agents.index') }}">{{ __('Annuler') }}</a>
        </div>
    </form>

    <h2>{{ __('Questions que cet agent sait traiter') }}</h2>
    <ul class="empty">
        @foreach ($questions as $q)
            <li>{{ $q }}</li>
        @endforeach
    </ul>

</x-agents::layouts.app>
