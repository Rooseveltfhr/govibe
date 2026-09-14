@extends('tagtoa::layouts.dashboard')
@section('title', __('Journal du stock'))
@section('page', __('Journal du stock'))

@php
    use Modules\Tagtoa\App\Support\Money;
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.inventory.index') }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Retour au stock') }}
    </a>
</div>

<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('À quoi ça sert') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:72ch">
        {{ __('Chaque ligne dit qui a fait bouger quel article, de combien, pourquoi, et quel était le stock avant et après.') }}
        {{ __('Quand la réserve ne correspond plus à l\'étagère, c\'est ici qu\'on retrouve le moment où le compte a divergé.') }}
        <br>
        {{ __('Rien ne s\'efface ni ne se modifie : une erreur se corrige par un nouveau mouvement.') }}
    </p>
</div>

{{-- ---------- Filtres ---------- --}}
<form method="GET" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    @if(! empty($filtres['ref']))
        <input type="hidden" name="ref" value="{{ $filtres['ref'] }}">
    @endif

    <label style="font-size:12px;color:var(--muted)">{{ __('Motif') }}
        <select name="type" class="inp" style="max-width:200px">
            <option value="">{{ __('Tous') }}</option>
            @foreach($types as $cle => $meta)
                <option value="{{ $cle }}" @selected(($filtres['type'] ?? '') === $cle)>{{ __($meta['label']) }}</option>
            @endforeach
        </select>
    </label>

    <label style="font-size:12px;color:var(--muted)">{{ __('Par qui') }}
        <select name="staff" class="inp" style="max-width:190px">
            <option value="">{{ __('Tout le monde') }}</option>
            @foreach($team as $m)
                <option value="{{ $m->id }}" @selected((string) ($filtres['staff'] ?? '') === (string) $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
    </label>

    <label style="font-size:12px;color:var(--muted)">{{ __('Du') }}
        <input type="date" name="from" class="inp" value="{{ $filtres['from'] ?? '' }}" style="max-width:160px">
    </label>
    <label style="font-size:12px;color:var(--muted)">{{ __('Au') }}
        <input type="date" name="to" class="inp" value="{{ $filtres['to'] ?? '' }}" style="max-width:160px">
    </label>

    <button class="btn btn-d btn-sm"><i class="fa-solid fa-filter"></i> {{ __('Filtrer') }}</button>
    @if(array_filter($filtres))
        <a href="{{ route('tagtoa.inventory.movements') }}" class="btn btn-o btn-sm">{{ __('Effacer') }}</a>
    @endif
</form>

@if($movements->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:34px 16px">
        <i class="fa-solid fa-clock-rotate-left" style="font-size:30px;opacity:.35"></i>
        <p style="margin-top:10px">{{ __('Aucun mouvement pour cette recherche.') }}</p>
    </div>
@else
<div class="card" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13.5px;min-width:780px">
        <thead>
            <tr style="text-align:left;color:var(--muted);font-size:12px">
                <th style="padding:8px 6px">{{ __('Quand') }}</th>
                <th style="padding:8px 6px">{{ __('Article') }}</th>
                <th style="padding:8px 6px">{{ __('Motif') }}</th>
                <th style="padding:8px 6px">{{ __('Écart') }}</th>
                <th style="padding:8px 6px">{{ __('Avant') }}</th>
                <th style="padding:8px 6px">{{ __('Après') }}</th>
                <th style="padding:8px 6px">{{ __('Par qui') }}</th>
                <th style="padding:8px 6px">{{ __('Raison') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($movements as $m)
            <tr style="border-top:1px solid var(--bd)">
                <td style="padding:9px 6px;white-space:nowrap;color:var(--muted)">
                    {{ optional($m->created_at)->format('d/m/Y H:i') }}
                </td>
                <td style="padding:9px 6px">
                    {{ $m->product_name ?: __('Article supprimé') }}
                    <span style="color:var(--muted);font-size:11px">· {{ $m->source === 'menu' ? __('Menu') : ($m->source === 'store' ? __('Boutique') : __('Caisse')) }}</span>
                </td>
                <td style="padding:9px 6px">{{ __($m->type_label) }}</td>
                <td style="padding:9px 6px;font-weight:600;white-space:nowrap;color:{{ $m->delta < 0 ? 'var(--red)' : '#2cb809' }}">
                    {{ $m->delta_label }}
                </td>
                <td style="padding:9px 6px;color:var(--muted)">{{ $fmt($m->stock_before) }}</td>
                <td style="padding:9px 6px">{{ $fmt($m->stock_after) }}</td>
                <td style="padding:9px 6px">{{ $m->actor_name ?: '—' }}</td>
                <td style="padding:9px 6px;color:var(--muted)">
                    {{ $m->reason ?: '—' }}
                    @if($m->supplier)
                        <span style="font-size:11px"> · {{ $m->supplier->name }}</span>
                    @endif
                    @if($m->unit_cost !== null)
                        <span style="font-size:11px"> · {{ Money::format((float) $m->unit_cost, $currency) }}/u</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:12px">{{ $movements->links() }}</div>
@endif
@endsection
