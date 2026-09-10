@extends('portail.layout')
@section('titre', 'Connexion')

@section('contenu')
<div class="pf-auth">
  <div class="pf-auth-tete">
    <h1>Espace client GOVIBE</h1>
    <p>Vos services, vos factures et vos commandes, au même endroit.</p>
  </div>

  @if ($errors->any())
    <div class="pf-avis pf-avis-erreur">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @if (session('succes'))
    <div class="pf-avis pf-avis-ok">{{ session('succes') }}</div>
  @endif

  <div class="pf-carte">
    <div class="pf-carte-corps">
      <form method="POST" action="{{ route('portail.connexion.post') }}">
        @csrf
        <div class="pf-champ">
          <label for="email">Adresse email</label>
          <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </div>
        <div class="pf-champ">
          <label for="password">Mot de passe</label>
          <input type="password" name="password" id="password" required autocomplete="current-password">
        </div>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.87rem;color:#64748b;margin-bottom:1rem">
          <input type="checkbox" name="memoire" value="1" style="width:auto"> Rester connecté
        </label>
        <button type="submit" class="pf-bouton">Se connecter</button>
      </form>
      <p class="pf-liens">
        Pas encore de compte ? <a href="{{ route('portail.inscription') }}">Créer un compte</a>
      </p>
    </div>
  </div>
</div>
@endsection
