@extends('portail.layout')
@section('titre', 'Vérifier votre adresse')

@section('contenu')
<div class="pf-auth">
  <div class="pf-auth-tete">
    <h1>Vérifiez votre adresse</h1>
    <p>Une dernière étape avant d'accéder à votre espace.</p>
  </div>

  <div class="pf-carte">
    <div class="pf-carte-corps">
      <div class="pf-avis pf-avis-attente" style="margin-bottom:1.2rem">
        Votre compte est créé pour <strong>{{ $compte->email }}</strong>, mais l'adresse
        n'est pas encore vérifiée.
      </div>

      <p style="color:#64748b;font-size:.9rem;line-height:1.7;margin:0 0 1.2rem">
        Cette vérification protège vos données : sans elle, n'importe qui pourrait créer
        un compte avec votre adresse et voir vos factures et vos commandes. Tant qu'elle
        n'est pas faite, votre espace reste vide.
      </p>

      <p style="color:#64748b;font-size:.9rem;line-height:1.7;margin:0 0 1.2rem">
        Écrivez-nous sur WhatsApp au <strong>+509 3398-8754</strong> depuis le numéro
        associé à votre compte, ou à <strong>contact@govibeht.com</strong> depuis cette
        adresse, et l'équipe active la vérification.
      </p>

      <a href="https://wa.me/50933988754?text={{ rawurlencode('Bonjour, je souhaite faire vérifier mon compte client GOVIBE : '.$compte->email) }}"
         target="_blank" rel="noopener"
         style="display:flex;align-items:center;justify-content:center;gap:.5rem;background:#25D366;color:#062e16;
                font-family:'Anton',sans-serif;letter-spacing:.04em;padding:.85rem 1.4rem;border-radius:50px;text-decoration:none">
        <i class="fab fa-whatsapp"></i> Demander la vérification
      </a>

      <form method="POST" action="{{ route('portail.deconnexion') }}" style="text-align:center;margin-top:1.1rem">
        @csrf
        <button type="submit" style="background:none;border:none;color:#94a3b8;font-family:inherit;font-size:.87rem;cursor:pointer">
          Se déconnecter
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
