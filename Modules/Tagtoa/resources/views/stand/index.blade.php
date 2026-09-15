@extends('tagtoa::layouts.dashboard')
@section('title', __('Mes stands'))
@section('page', __('Vos Smart Stands'))

@section('content')
<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('Comment ça marche') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:72ch">
        {{ __('Le QR et la puce de chaque stand sont gravés une fois pour toutes. Ce que le client voit en scannant, vous pouvez le changer quand vous voulez — sans jamais rien réimprimer.') }}
        <br>
        {{ __('Pour activer un stand : grattez le film au dos et scannez-le avec votre téléphone.') }}
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-p" href="{{ route('tagtoa.stand.activate') }}">
            <i class="fa-solid fa-camera"></i> {{ __('Activer mes stands') }}
        </a>
        {{-- Le jour où l'on vend son commerce, on cherche ce bouton ici. --}}
        <a class="btn btn-o" href="{{ route('tagtoa.stand.transfer.index') }}">
            <i class="fa-solid fa-right-left"></i> {{ __('Céder / Reprendre') }}
        </a>
    </div>
</div>

@if($stands->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:34px 16px">
        <i class="fa-solid fa-qrcode" style="font-size:30px;opacity:.35"></i>
        <p style="margin-top:10px">{{ __('Aucun stand activé pour le moment.') }}</p>
    </div>
@else
<div class="card" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:14.5px;min-width:560px">
        <thead>
            <tr style="text-align:left;color:var(--muted);font-size:12px">
                <th style="padding:8px 6px">{{ __('Stand') }}</th>
                <th style="padding:8px 6px">{{ __('Emplacement') }}</th>
                <th style="padding:8px 6px">{{ __('État') }}</th>
                <th style="padding:8px 6px" title="{{ __('Nombre d\'heures pendant lesquelles ce stand a servi — pas le nombre de clients.') }}">{{ __('Activité') }}</th>
                <th style="padding:8px 6px"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($stands as $s)
            <tr style="border-top:1px solid var(--bd)">
                <td style="padding:10px 6px;font-family:monospace;font-weight:600">{{ $s->public_id }}</td>
                <td style="padding:10px 6px">
                    <form method="POST" action="{{ route('tagtoa.stand.update', $s->id) }}"
                          style="display:flex;gap:6px;align-items:center">
                        @csrf @method('PUT')
                        <input class="inp" name="location_label" maxlength="60"
                               value="{{ $s->location_label }}" style="max-width:190px"
                               placeholder="{{ __('ex. Table 05') }}">
                        <button class="btn btn-o btn-sm"><i class="fa-solid fa-floppy-disk"></i></button>
                    </form>
                </td>
                <td style="padding:10px 6px">
                    <span style="background:rgba(44,184,9,.12);color:#1a7a05;border-radius:999px;
                                 padding:3px 10px;font-size:11.5px;font-weight:700">
                        {{ $s->digital_label }}
                    </span>
                </td>
                {{-- Compte les HEURES d'activité, pas les clients : compter
                     chaque scan écrirait dans la base sur la requête la plus
                     fréquente de la plateforme. --}}
                <td style="padding:10px 6px;font-variant-numeric:tabular-nums">
                    {{ $s->scan_count }}
                    @if($s->last_scanned_at)
                        <span style="display:block;color:var(--muted);font-size:11.5px">
                            {{ $s->last_scanned_at->diffForHumans() }}
                        </span>
                    @endif
                </td>
                <td style="padding:10px 6px;text-align:right">
                    <a class="btn btn-o btn-sm" target="_blank" rel="noopener"
                       href="{{ url($s->path) }}" title="{{ __('Ouvrir comme un client') }}">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
