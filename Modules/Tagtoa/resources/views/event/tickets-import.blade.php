@extends('tagtoa::layouts.dashboard')
@section('title', __('Import billets imprimés'))
@section('page', $event->title)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
</div>

<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-boxes-stacked"></i></div><div class="v">{{ $stats['imported'] }}</div><div class="k">{{ __('Billets importés') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-hourglass-half"></i></div><div class="v">{{ $stats['unsold'] }}</div><div class="k">{{ __('En attente de vente') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div><div class="v">{{ $stats['sold'] }}</div><div class="k">{{ __('Vendus') }}</div></div>
</div>

<div class="card" style="margin-top:16px">
    <h2>{{ __('Importer un lot de billets déjà imprimés') }}</h2>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">
        {{ __('Fichier CSV, une ligne par billet : le code du QR imprimé, puis (optionnel) le second code imprimé au dos, séparés par une virgule. Exemple :') }}
        <code>696bb99d76e28407,25H165407</code>
    </p>
    <form method="POST" action="{{ route('tagtoa.event.dashboard.tickets.import.store', $event->id) }}" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        @csrf
        <input class="inp" type="file" name="file" accept=".csv,text/csv,text/plain" required style="flex:1;min-width:220px">
        <button class="btn btn-p"><i class="fa-solid fa-upload"></i> {{ __('Importer') }}</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('En attente de vente') }} ({{ $pending->total() }})</h2></div>
    @if($pending->isEmpty())
        <div class="empty"><i class="fa-solid fa-ticket"></i>{{ __('Aucun billet importé en attente. Importez un fichier ci-dessus.') }}</div>
    @else
    <div style="overflow-x:auto"><table>
        <tr><th>{{ __('Code (QR)') }}</th><th>{{ __('Serial (dos)') }}</th><th>{{ __('Importé le') }}</th><th></th></tr>
        @foreach($pending as $t)
        <tr>
            <td><code>{{ $t->code }}</code></td>
            <td>{{ $t->serial ?: '—' }}</td>
            <td style="color:var(--muted)">{{ $t->created_at->format('d/m/y H:i') }}</td>
            <td>
                <form method="POST" action="{{ route('tagtoa.event.dashboard.tickets.destroy', [$event->id, $t->id]) }}" onsubmit="return confirm('{{ __('Retirer ce billet de la liste ?') }}')" style="display:inline">@csrf @method('DELETE')
                    <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button>
                </form>
            </td>
        </tr>
        @endforeach
    </table></div>
    <div style="margin-top:14px">{{ $pending->links() }}</div>
    @endif
</div>
@endsection
