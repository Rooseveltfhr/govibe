@extends('layouts.admin', ['title' => 'Stands'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Stands</h1>
    <a href="{{ route('admin.booths.create') }}" class="btn btn-fp-primary btn-sm">+ Nouveau stand</a>
</div>
@foreach ($booths as $zone => $zoneBooths)
    <h2 class="h5 mt-3">Zone {{ $zone }}</h2>
    <div class="fp-card p-3 mb-3">
        <table class="table fp-table mb-0">
            <thead><tr><th>Code</th><th>Taille</th><th>Prix</th><th>Statut</th><th>Exposant</th><th></th></tr></thead>
            <tbody>
                @foreach ($zoneBooths as $booth)
                    <tr>
                        <td><strong>{{ $booth->code }}</strong></td>
                        <td>{{ $booth->size }} m</td>
                        <td>{{ number_format($booth->price, 0, ',', ' ') }} HTG</td>
                        <td>
                            @if ($booth->status === 'available')<span class="badge text-bg-success">Disponible</span>
                            @elseif ($booth->status === 'reserved')<span class="badge text-bg-warning text-dark">Réservé</span>
                            @else<span class="badge text-bg-danger">Vendu</span>@endif
                        </td>
                        <td class="small">{{ $booth->exhibitor?->company ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.booths.edit', $booth) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                            <form method="post" action="{{ route('admin.booths.destroy', $booth) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endsection
