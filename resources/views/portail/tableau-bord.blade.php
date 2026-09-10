@extends('portail.layout')
@section('titre', 'Tableau de bord')

@section('contenu')
<div class="pf-page">

  @if (session('succes'))
    <div class="pf-avis pf-avis-ok">{{ session('succes') }}</div>
  @endif

  <h1 class="pf-titre">Bonjour {{ $compte->nom }}</h1>
  <p class="pf-sous">{{ $client->name }} &middot; compte {{ $client->reference_number }}</p>

  <div class="pf-stats">
    <div class="pf-stat">
      <span>Services en cours</span>
      <strong>{{ collect($services)->where('etat', '!=', 'termine')->count() }}</strong>
    </div>
    <div class="pf-stat">
      <span>Factures à régler</span>
      <strong class="num">{{ number_format((float) $aRegler, 2, ',', ' ') }}</strong>
    </div>
    <div class="pf-stat">
      <span>Dernière connexion</span>
      <strong style="font-size:1rem">
        {{ $compte->dernier_login_le?->format('d/m/Y H:i') ?? 'Première visite' }}
      </strong>
    </div>
  </div>

  <div class="pf-carte">
    <div class="pf-carte-tete">
      <h2>Vos services</h2>
      <a href="{{ route('portail.services') }}" style="font-size:.83rem">Tout voir</a>
    </div>
    @forelse (array_slice($services, 0, 6) as $s)
      <div class="pf-ligne">
        <span class="ico"><i class="fas {{ $s['icone'] }}"></i></span>
        <span class="txt">
          <strong>{{ $s['titre'] }}</strong>
          <small>{{ $s['unite'] }}@if ($s['detail']) &middot; {{ $s['detail'] }} @endif &middot; {{ $s['reference'] }}</small>
        </span>
        <span class="pf-etat e-{{ $s['etat'] }}">{{ $s['statut'] }}</span>
      </div>
    @empty
      <div class="pf-vide">
        <i class="fas fa-inbox"></i>
        Aucun service rattaché à ce compte pour le moment.
      </div>
    @endforelse
  </div>

  <div class="pf-carte">
    <div class="pf-carte-tete">
      <h2>Dernières factures</h2>
      <a href="{{ route('portail.factures') }}" style="font-size:.83rem">Tout voir</a>
    </div>
    @if ($factures->isEmpty())
      <div class="pf-vide">
        <i class="fas fa-file-invoice"></i>
        Aucune facture pour le moment.
      </div>
    @else
      <table>
        <thead>
          <tr><th>Référence</th><th>Émise le</th><th>Statut</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
          @foreach ($factures as $f)
            <tr>
              <td><strong>{{ $f->reference }}</strong></td>
              <td>{{ $f->issued_date?->format('d/m/Y') ?? '—' }}</td>
              <td>{{ ucfirst((string) $f->status) }}</td>
              <td class="num">{{ number_format((float) $f->total, 2, ',', ' ') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>
@endsection
