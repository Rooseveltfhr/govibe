{{--
    TAGTOA — options d'unité pour un <select>, adaptées au type de commerce.

    Une SUGGESTION, jamais une restriction : toutes les unités de
    Pricing::UNITS restent choisissables, une pharmacie qui vend aussi des
    biberons à la pièce n'est pas bloquée. Seul l'ORDRE change, pour que les
    unités les plus probables du métier soient en tête plutôt qu'à chercher
    dans une liste de douze.

    Variables attendues :
      - $suggested  (array, optionnel) : clés de Pricing::UNITS à mettre en avant
      - $selected   (string, optionnel) : unité déjà choisie
--}}
@php
    $suggested = $suggested ?? [];
    $selected = $selected ?? null;
    $toutes = \Modules\Tagtoa\App\Support\Catalog\Pricing::UNITS;
    $autres = array_diff_key($toutes, array_flip($suggested));
@endphp
@if($suggested)
    <optgroup label="{{ __('Suggérées pour votre activité') }}">
        @foreach($suggested as $cle)
            @if(isset($toutes[$cle]))
                <option value="{{ $cle }}" @selected($selected === $cle)>{{ __($toutes[$cle]['label']) }}</option>
            @endif
        @endforeach
    </optgroup>
    <optgroup label="{{ __('Autres unités') }}">
        @foreach($autres as $cle => $u)
            <option value="{{ $cle }}" @selected($selected === $cle)>{{ __($u['label']) }}</option>
        @endforeach
    </optgroup>
@else
    @foreach($toutes as $cle => $u)
        <option value="{{ $cle }}" @selected($selected === $cle)>{{ __($u['label']) }}</option>
    @endforeach
@endif
