{{-- Champs d'un service d'hôtel ($s = service existant ou null). Le serveur revalide tout. --}}
@php $v = fn ($k, $d = null) => $s ? ($s->{$k} ?? $d) : old($k, $d); @endphp
<div class="row">
    <div>
        <select class="sel" name="kind" required>
            @foreach($kinds as $k => $meta)<option value="{{ $k }}" @selected($v('kind') === $k)>{{ __($meta['label']) }}</option>@endforeach
        </select>
    </div>
    <div><input class="inp" name="name" maxlength="80" required placeholder="{{ __('Nom (ex. Serviettes supplémentaires)') }}" value="{{ $v('name') }}"></div>
</div>
<div class="row" style="margin-top:8px">
    <div><input class="inp" type="number" step="0.01" min="0" name="price" placeholder="{{ __('Prix (vide = gratuit)') }}" value="{{ $v('price') }}"></div>
    <div>
        <select class="sel" name="team">
            <option value="">{{ __('Équipe (automatique)') }}</option>
            @foreach($teams as $k => $l)<option value="{{ $k }}" @selected($v('team') === $k)>{{ __($l) }}</option>@endforeach
        </select>
    </div>
</div>
<div style="margin-top:8px">
    <select class="sel" name="menu_id">
        <option value="">{{ __('Menu du room service (obligatoire pour le room service)') }}</option>
        @foreach($menus as $m)<option value="{{ $m->id }}" @selected((string) $v('menu_id') === (string) $m->id)>{{ $m->name }}</option>@endforeach
    </select>
    @if($menus->isEmpty())<div style="color:var(--muted);font-size:12px;margin-top:4px">{{ __('Créez d\'abord un menu pour proposer le room service.') }}</div>@endif
</div>
<input class="inp" name="description" maxlength="300" placeholder="{{ __('Description (facultatif)') }}" value="{{ $v('description') }}" style="margin-top:8px">
<details style="margin-top:8px">
    <summary style="cursor:pointer;font-size:13px">{{ __('Nom dans les autres langues') }}</summary>
    <div class="row" style="margin-top:6px;flex-wrap:wrap">
        @foreach($locales as $l)
            <div><input class="inp" name="translations[{{ $l }}]" maxlength="80" placeholder="{{ strtoupper($l) }}" value="{{ $s ? ($s->translations[$l] ?? '') : old('translations.'.$l) }}"></div>
        @endforeach
    </div>
</details>
