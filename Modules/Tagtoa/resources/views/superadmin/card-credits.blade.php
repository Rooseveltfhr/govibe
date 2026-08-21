@extends('tagtoa::layouts.dashboard')
@section('title', __('Crédits d\'activation'))
@section('page', __('Crédits d\'activation de cartes'))

@section('content')

<div class="card" style="margin-bottom:18px">
    <div class="h-row"><h2><i class="fa-solid fa-coins"></i> {{ __('Accorder des crédits') }}</h2></div>
    <p style="color:var(--muted);margin:0 0 14px">{{ __('Vendez/accordez des crédits d\'activation de cartes officielles TAGTOA à un marchand ou revendeur (par tenant_id). Chaque activation de carte au-delà du quota du forfait consomme 1 crédit — sans envoi de matériel.') }}</p>
    <form method="POST" action="{{ route('tagtoa.superadmin.credits.grant') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        @csrf
        <div style="flex:1;min-width:200px">
            <label class="lbl">{{ __('Tenant (marchand/revendeur)') }}</label>
            <input class="inp" name="tenant_id" placeholder="tenant_id" required>
        </div>
        <div style="width:160px">
            <label class="lbl">{{ __('Nombre de crédits') }}</label>
            <input class="inp" name="qty" type="number" min="1" max="1000000" value="50" required>
        </div>
        <button class="btn btn-p" type="submit"><i class="fa-solid fa-plus"></i> {{ __('Accorder') }}</button>
    </form>
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Soldes par tenant') }}</h2></div>
    @if($credits->isEmpty())
        <div class="empty"><i class="fa-solid fa-coins"></i>{{ __('Aucun crédit accordé pour le moment.') }}</div>
    @else
        <table>
            <thead><tr>
                <th>{{ __('Tenant') }}</th>
                <th>{{ __('Accordés') }}</th>
                <th>{{ __('Utilisés') }}</th>
                <th>{{ __('Disponibles') }}</th>
            </tr></thead>
            <tbody>
            @foreach($credits as $c)
                <tr>
                    <td><code>{{ $c->tenant_id }}</code></td>
                    <td>{{ $c->granted }}</td>
                    <td>{{ $c->used }}</td>
                    <td><b>{{ $c->available }}</b></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px">{{ $credits->links() }}</div>
    @endif
</div>
@endsection
