@extends('portail.layout')
@section('titre', 'Mes services')

@section('contenu')
<div class="pf-page">
  <h1 class="pf-titre">Mes services</h1>
  <p class="pf-sous">Tout ce que vous avez avec GOVIBE, toutes activités confondues.</p>

  <div class="pf-carte">
    @forelse ($services as $s)
      <div class="pf-ligne">
        <span class="ico"><i class="fas {{ $s['icone'] }}"></i></span>
        <span class="txt">
          <strong>{{ $s['titre'] }}</strong>
          <small>
            {{ $s['unite'] }}@if ($s['detail']) &middot; {{ $s['detail'] }} @endif
            &middot; {{ $s['reference'] }} &middot; {{ $s['date']?->format('d/m/Y') }}
          </small>
        </span>
        <span class="pf-etat e-{{ $s['etat'] }}">{{ $s['statut'] }}</span>
      </div>
    @empty
      <div class="pf-vide">
        <i class="fas fa-inbox"></i>
        Aucun service rattaché à ce compte.
      </div>
    @endforelse
  </div>
</div>
@endsection
