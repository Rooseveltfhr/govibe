{{-- HM-2 — champs d'un tarif (nuit / moment). $p = le tarif à modifier, ou null pour un nouveau. --}}
@php
    $kind = old('kind', $p->kind ?? 'night');
    $sel = old('inclusions', $p->inclusions['keys'] ?? []);
    $custom = old('custom', isset($p) && $p ? implode("\n", $p->inclusions['custom'] ?? []) : '');
    $uid = 'pf'.($p->id ?? 'new').'_'.($t->id ?? 0);
@endphp
<div class="duo trio">
    <select name="kind" class="inp plan-kind" onchange="var h=this.closest('form').querySelector('.plan-hours'); if(h){h.style.display=(this.value==='block')?'':'none';}" title="{{ __('Type de tarif') }}">
        @foreach($kinds as $k => $lbl)<option value="{{ $k }}" @selected($kind === $k)>{{ __($lbl) }}</option>@endforeach
    </select>
    <select name="hours" class="inp plan-hours" style="{{ $kind === 'block' ? '' : 'display:none' }}" title="{{ __('Durée du moment') }}">
        @foreach($hours as $h)<option value="{{ $h }}" @selected((int) old('hours', $p->hours ?? 2) === $h)>{{ $h }} h</option>@endforeach
    </select>
    <input name="price" class="inp" type="number" step="0.01" min="0" required placeholder="{{ __('Prix') }}" value="{{ old('price', isset($p) && $p ? (float) $p->price : '') }}">
</div>
<div class="row pair" style="margin-top:8px">
    <select name="currency" class="inp fit" title="{{ __('Devise') }}">
        @foreach($devises as $code => $lbl)<option value="{{ $code }}" @selected(strtoupper(old('currency', $p->currency ?? $hotel->currency)) === $code)>{{ $code }}</option>@endforeach
    </select>
</div>
<div style="margin-top:10px">
    <div style="font-size:12.5px;color:var(--muted);margin-bottom:4px">{{ __('Inclus dans ce prix') }}</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:4px 10px">
        @foreach($inclusions as $key => $meta)
            <label class="switch" style="flex:0"><input type="checkbox" name="inclusions[]" value="{{ $key }}" @checked(in_array($key, (array) $sel, true))> <i class="fa-solid {{ $meta['icon'] }}" style="color:var(--muted)"></i> {{ __($meta['label']) }}</label>
        @endforeach
    </div>
    <textarea name="custom" class="inp" rows="2" maxlength="500" style="margin-top:6px" placeholder="{{ __('Autres (une par ligne) : jacuzzi, bouteille de rhum offerte…') }}">{{ $custom }}</textarea>
</div>
