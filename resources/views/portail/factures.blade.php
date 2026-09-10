@extends('portail.layout')
@section('titre', 'Factures')

@section('contenu')
<div class="pf-page">
  <h1 class="pf-titre">Factures</h1>
  <p class="pf-sous">Vos factures GOVIBE, toutes activités confondues.</p>

  <div class="pf-carte">
    @if ($factures->isEmpty())
      <div class="pf-vide">
        <i class="fas fa-file-invoice"></i>
        Aucune facture pour le moment.
      </div>
    @else
      <table>
        <thead>
          <tr><th>Référence</th><th>Émise le</th><th>Échéance</th><th>Statut</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
          @foreach ($factures as $f)
            <tr>
              <td><strong>{{ $f->reference }}</strong></td>
              <td>{{ $f->issued_date?->format('d/m/Y') ?? '—' }}</td>
              <td>{{ $f->due_date?->format('d/m/Y') ?? '—' }}</td>
              <td>{{ ucfirst((string) $f->status) }}</td>
              <td class="num">{{ number_format((float) $f->total, 2, ',', ' ') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  @if ($factures->hasPages())
    <div style="margin-top:1rem">{{ $factures->links() }}</div>
  @endif
</div>
@endsection
