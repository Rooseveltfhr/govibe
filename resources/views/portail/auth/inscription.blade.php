@extends('portail.layout')
@section('titre', 'Créer un compte')

@section('contenu')
<div class="pf-auth">
  <div class="pf-auth-tete">
    <h1>Créer votre compte</h1>
    <p>Un seul compte pour tous vos services GOVIBE.</p>
  </div>

  @if ($errors->any())
    <div class="pf-avis pf-avis-erreur">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="pf-carte">
    <div class="pf-carte-corps">
      <form method="POST" action="{{ route('portail.inscription.post') }}">
        @csrf
        <div class="pf-champ">
          <label for="nom">Votre nom</label>
          <input type="text" name="nom" id="nom" value="{{ old('nom') }}" required maxlength="150" autocomplete="name" autofocus>
        </div>
        <div class="pf-champ">
          <label for="entreprise">Entreprise <span class="aide">Facultatif</span></label>
          <input type="text" name="entreprise" id="entreprise" value="{{ old('entreprise') }}" maxlength="200" autocomplete="organization">
        </div>
        <div class="pf-champ">
          <label for="email">Adresse email
            <span class="aide">Utilisez celle de vos commandes : votre historique s'y rattache</span>
          </label>
          <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
        </div>
        <div class="pf-champ">
          <label for="telephone">WhatsApp <span class="aide">Facultatif</span></label>
          <input type="tel" name="telephone" id="telephone" value="{{ old('telephone') }}" maxlength="40" autocomplete="tel" placeholder="+509 ...">
        </div>
        <div class="pf-champ">
          <label for="password">Mot de passe
            <span class="aide">8 caractères minimum, avec au moins une lettre et un chiffre</span>
          </label>
          <input type="password" name="password" id="password" required autocomplete="new-password">
        </div>
        <div class="pf-champ">
          <label for="password_confirmation">Confirmer le mot de passe</label>
          <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit" class="pf-bouton">Créer mon compte</button>
      </form>
      <p class="pf-liens">
        Vous avez déjà un compte ? <a href="{{ route('portail.connexion') }}">Se connecter</a>
      </p>
    </div>
  </div>
</div>
@endsection
