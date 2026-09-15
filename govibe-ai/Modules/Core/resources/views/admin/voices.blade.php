@php
    $languageLabels = ['ht' => 'Kreyòl', 'fr' => 'Français', 'en' => 'English', 'es' => 'Español'];

    // Gwoupe pa lang: se konsa yon moun gade yon bibliyotèk vwa — « ki vwa
    // mwen genyen pou kreyòl? » — pa nan yon sèl lis alfabetik.
    $byLanguage = [];
    foreach ($mine as $voice) {
        $byLanguage[$voice->language][] = $voice;
    }
@endphp

<x-core::layouts.admin :title="__('Voix')">

    <h1>{{ __('Bibliothèque de voix') }}</h1>
    <p class="lead">
        {{ __("Les voix que vous choisissez, et la langue de chacune. Un agent qui n'a pas sa propre voix prend la voix par défaut de sa langue.") }}
    </p>

    @error('voice_id') <div class="note">{{ $message }}</div> @enderror
    @error('samples') <div class="note">{{ $message }}</div> @enderror

    @unless ($connected)
        <div class="note">
            <strong>{{ __("Aucune clé de voix n'est configurée sur ce serveur.") }}</strong>
            {{ __("Posez une clé ElevenLabs dans Configuration : sans elle, la liste du fournisseur reste vide et aucune voix ne peut être enregistrée.") }}
        </div>
    @endunless

    {{-- ─── Bibliyotèk nou ─── --}}
    <h2>{{ __('Vos voix') }}</h2>

    @if ($mine->isEmpty())
        <p class="empty">{{ __("Aucune voix dans la bibliothèque. Prenez-en une chez le fournisseur ci-dessous, ou enregistrez la vôtre.") }}</p>
    @else
        @foreach ($languageLabels as $code => $label)
            @if (! empty($byLanguage[$code]))
                <h2>{{ $label }}</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('Nom') }}</th>
                            <th>{{ __('Origine') }}</th>
                            <th>{{ __('Écouter') }}</th>
                            <th>{{ __('Langue et défaut') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($byLanguage[$code] as $voice)
                            <tr>
                                <td>
                                    {{ $voice->name }}
                                    @if ($voice->is_default)
                                        <span class="pill on">{{ __('Par défaut') }}</span>
                                    @endif
                                    <br><span class="empty mono">{{ $voice->voice_id }}</span>
                                </td>
                                <td class="empty">
                                    {{ $voice->isCloned() ? __('Enregistrée par vous') : __('Bibliothèque du fournisseur') }}
                                </td>
                                <td>
                                    @if ($voice->preview_url)
                                        <audio controls preload="none" src="{{ $voice->preview_url }}" style="height:32px;max-width:200px"></audio>
                                    @else
                                        <span class="empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.voices.update', $voice) }}">
                                        @csrf
                                        <input type="text" name="name" value="{{ $voice->name }}" maxlength="120" style="max-width:180px">
                                        <div class="row" style="margin-top:.4rem">
                                            <select name="language" style="max-width:130px">
                                                @foreach ($languageLabels as $value => $langLabel)
                                                    <option value="{{ $value }}" @selected($voice->language === $value)>{{ $langLabel }}</option>
                                                @endforeach
                                            </select>
                                            <label style="margin:0;font-weight:400;display:flex;gap:.35rem;align-items:center">
                                                <input type="checkbox" name="is_default" value="1" @checked($voice->is_default) style="width:auto">
                                                <span class="empty">{{ __('Par défaut') }}</span>
                                            </label>
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Enregistrer') }}</button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.voices.destroy', $voice) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Retirer') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    @endif

    {{-- ─── Vwa kreyòl: sa ki fè diferans lan ─── --}}
    @if (empty($byLanguage['ht']))
        <div class="note">
            {{ __("Aucune voix marquée pour le créole. ElevenLabs n'en livre pas : enregistrez-en une plus bas avec des échantillons d'une personne qui parle créole.") }}
        </div>
    @endif

    {{-- ─── Sa founisè a genyen ─── --}}
    <h2>{{ __('Disponibles chez le fournisseur') }}</h2>

    @if ($available === [])
        <p class="empty">
            {{ $connected ? __('Toutes les voix du fournisseur sont déjà dans votre bibliothèque.') : __('Aucune voix disponible.') }}
        </p>
    @else
        <p class="lead">{{ count($available) }} {{ __('voix non encore ajoutées. Prenez celles qui vous servent.') }}</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Voix') }}</th>
                    <th>{{ __('Écouter') }}</th>
                    <th>{{ __('Ajouter comme') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($available as $voice)
                    <tr>
                        <td>
                            {{ $voice->name }}
                            @if ($voice->isMine())
                                <span class="pill on">{{ __('Enregistrée par vous') }}</span>
                            @endif
                            <br>
                            <span class="empty">{{ $voice->description ?: implode(' · ', $voice->labels) }}</span>
                        </td>
                        <td>
                            @if ($voice->previewUrl)
                                <audio controls preload="none" src="{{ $voice->previewUrl }}" style="height:32px;max-width:200px"></audio>
                            @else
                                <span class="empty">—</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.voices.store') }}">
                                @csrf
                                <input type="hidden" name="voice_id" value="{{ $voice->id }}">
                                <input type="hidden" name="name" value="{{ $voice->name }}">
                                <div class="row">
                                    <select name="language" style="max-width:130px">
                                        @foreach ($languageLabels as $value => $langLabel)
                                            <option value="{{ $value }}" @selected($value === 'fr')>{{ $langLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Ajouter') }}</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ─── Klonaj ─── --}}
    <h2>{{ __('Enregistrer une voix') }}</h2>
    <form method="POST" action="{{ route('admin.voices.clone') }}" enctype="multipart/form-data">
        @csrf
        <fieldset>
            <legend>{{ __('Nouvelle voix') }}</legend>
            <div class="grid2">
                <div>
                    <label for="vname">{{ __('Nom de la voix') }}</label>
                    <input type="text" id="vname" name="name" maxlength="120" required value="{{ old('name') }}">
                    @error('name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="vlang">{{ __('Langue') }}
                        <span class="hint">{{ __("Pour le créole, utilisez des échantillons d'une personne qui parle créole.") }}</span>
                    </label>
                    <select id="vlang" name="language">
                        @foreach ($languageLabels as $value => $langLabel)
                            <option value="{{ $value }}" @selected(old('language', 'ht') === $value)>{{ $langLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label for="vdesc">{{ __('Description (facultatif)') }}</label>
            <input type="text" id="vdesc" name="description" maxlength="500" value="{{ old('description') }}">

            <label for="vsamples">{{ __('Échantillons audio') }}
                <span class="hint">{{ __("Une à cinq minutes de parole claire, sans bruit de fond. Les fichiers partent au fournisseur puis sont effacés — nous n'en gardons aucun.") }}</span>
            </label>
            <input type="file" id="vsamples" name="samples[]" accept="audio/*" multiple required>
        </fieldset>

        <div class="row">
            <button type="submit" class="btn btn-primary" @disabled(! $connected)>{{ __('Enregistrer la voix') }}</button>
        </div>
    </form>

</x-core::layouts.admin>
