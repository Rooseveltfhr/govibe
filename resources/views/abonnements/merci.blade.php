@extends('layouts.public')

@section('title', 'Commande '.$commande->reference.' enregistrée | GOVIBE Innovation Hub')
@section('description', 'Votre commande GOVIBE est enregistrée. Notre équipe vérifie le règlement et met le service en route.')

@section('head')
<style>
  .mr { --mr-rouge:#DC2626; --mr-sombre:#991b1b; --mr-encre:#172033;
        --mr-gris:#667085; --mr-fond:#f7f8fa; --mr-trait:#e4e7ec; }

  .mr-sect { background:var(--mr-fond); padding:44px 0 56px; }
  .mr-wrap { max-width:680px; margin:auto; padding:0 16px; }

  .mr-carte { background:#fff; border:1px solid var(--mr-trait); border-radius:16px; padding:30px 24px; text-align:center; }
  .mr-coche {
    width:62px; height:62px; border-radius:50%; margin:0 auto 16px;
    background:#ecfdf5; color:#047857; display:flex; align-items:center; justify-content:center; font-size:27px;
  }
  .mr-carte h1 {
    font-family:'Anton',sans-serif; font-weight:400; letter-spacing:.5px; margin:0;
    font-size:clamp(24px,5vw,33px); color:var(--mr-encre); text-wrap:balance;
  }
  .mr-carte > p { margin:12px auto 0; max-width:48ch; font-size:14.5px; line-height:1.7; color:var(--mr-gris); }

  .mr-ref {
    display:inline-block; margin-top:18px; background:var(--mr-fond); border:1px dashed var(--mr-trait);
    border-radius:11px; padding:11px 18px;
  }
  .mr-ref span { display:block; font-size:11.5px; letter-spacing:.12em; text-transform:uppercase; color:var(--mr-gris); }
  .mr-ref strong { font-size:20px; font-weight:800; color:var(--mr-encre); letter-spacing:1px; }

  .mr-bloc { background:#fff; border:1px solid var(--mr-trait); border-radius:14px; padding:20px 22px; margin-top:16px; }
  .mr-bloc h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:var(--mr-encre); }
  .mr-ligne { display:flex; justify-content:space-between; gap:12px; font-size:13.5px; padding:8px 0; border-bottom:1px dashed var(--mr-trait); }
  .mr-ligne:last-child { border-bottom:0; }
  .mr-ligne span { color:var(--mr-gris); }
  .mr-ligne strong { color:var(--mr-encre); text-align:right; word-break:break-word; }

  .mr-etapes { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:14px; }
  .mr-etape { display:flex; gap:12px; align-items:flex-start; }
  .mr-etape i {
    width:26px; height:26px; border-radius:50%; background:#fef2f2; color:var(--mr-rouge);
    display:flex; align-items:center; justify-content:center; font-size:12px; flex:0 0 auto; margin-top:1px;
  }
  .mr-etape div { font-size:13.5px; line-height:1.6; color:var(--mr-gris); }
  .mr-etape strong { display:block; color:var(--mr-encre); font-size:14px; margin-bottom:2px; }

  .mr-wa {
    display:flex; align-items:center; justify-content:center; gap:10px; margin-top:16px;
    background:#25D366; color:#fff; border-radius:12px; padding:15px 18px;
    font-size:15px; font-weight:800; text-decoration:none;
  }
  .mr-liens { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-top:16px; }
  .mr-liens a {
    font-size:13.5px; font-weight:700; text-decoration:none; color:var(--mr-sombre);
    background:#fff; border:1px solid var(--mr-trait); border-radius:10px; padding:11px 16px;
  }
  .mr-alerte { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:12px; padding:14px 16px; font-size:13.5px; line-height:1.65; margin-top:16px; }
</style>
@endsection

@section('content')
<div class="mr">
  <section class="mr-sect">
    <div class="mr-wrap">

      <div class="mr-carte">
        <div class="mr-coche"><i class="fas fa-check"></i></div>
        <h1>Votre commande est enregistrée</h1>
        <p>
          Merci {{ $commande->nom_complet }}. Elle est arrivée chez nous, avec le
          montant et le moyen de paiement que vous avez choisis. Notre équipe
          vérifie le règlement, puis met le service en route.
        </p>

        <div class="mr-ref">
          <span>Votre référence</span>
          <strong>{{ $commande->reference }}</strong>
        </div>
      </div>

      @if ($avertissement)
        <div class="mr-alerte">{{ $avertissement }}</div>
      @endif

      <div class="mr-bloc">
        <h2>Ce que vous avez commandé</h2>
        <div class="mr-ligne"><span>Service</span><strong>{{ $commande->service_libelle }}</strong></div>
        <div class="mr-ligne"><span>Offre</span><strong>{{ $commande->plan_nom }}</strong></div>
        <div class="mr-ligne"><span>Facturation</span><strong>{{ $commande->cycle_libelle }}</strong></div>

        @if ($commande->domaine)
          <div class="mr-ligne">
            <span>Nom de domaine</span>
            <strong>{{ $commande->domaine }}</strong>
          </div>
        @endif
        @if ($commande->origine_domaine_libelle)
          <div class="mr-ligne"><span>Situation du domaine</span><strong>{{ $commande->origine_domaine_libelle }}</strong></div>
        @endif
        @if ($commande->duree_annees)
          <div class="mr-ligne">
            <span>Durée</span>
            <strong>{{ $commande->duree_annees }} an{{ $commande->duree_annees > 1 ? 's' : '' }}</strong>
          </div>
        @endif

        <div class="mr-ligne">
          <span>Montant</span>
          <strong>
            {{ $commande->montant_affiche }}
            @if ($commande->montant_origine_affiche)
              <br><span style="font-size:12px;font-weight:600">soit {{ $commande->montant_origine_affiche }}</span>
            @endif
          </strong>
        </div>
        <div class="mr-ligne"><span>Moyen de paiement</span><strong>{{ $commande->passerelle_nom }}</strong></div>
        @if ($commande->preuve)
          <div class="mr-ligne"><span>Preuve reçue</span><strong>{{ $commande->preuve->reference }}</strong></div>
        @endif
      </div>

      <div class="mr-bloc">
        <h2>Ce qui se passe maintenant</h2>
        <ul class="mr-etapes">
          <li class="mr-etape">
            <i class="fas fa-1"></i>
            <div>
              <strong>Vérification du règlement</strong>
              @if ($commande->mode_paiement === 'api')
                Nous confirmons le paiement auprès de {{ $commande->passerelle_nom }}.
              @else
                Un membre de l'équipe contrôle la preuve que vous venez d'envoyer.
              @endif
            </div>
          </li>
          <li class="mr-etape">
            <i class="fas fa-2"></i>
            <div>
              <strong>Mise en service</strong>
              Nous préparons votre {{ mb_strtolower($commande->service_libelle) }}
              @if ($commande->domaine) pour {{ $commande->domaine }} @endif
              et vous envoyons vos accès.
            </div>
          </li>
          <li class="mr-etape">
            <i class="fas fa-3"></i>
            <div>
              <strong>Votre portail</strong>
              Créez votre compte avec <strong style="display:inline">{{ $commande->email }}</strong> :
              cette commande et vos factures s'y retrouveront, avec vos autres services GOVIBE.
            </div>
          </li>
        </ul>
      </div>

      {{-- wa.me ne peut pas transporter de fichier : la preuve est déjà arrivée
           par le formulaire. Ce bouton ne fait que prévenir l'équipe. --}}
      <a class="mr-wa" href="{{ $commande->lien_whatsapp }}" target="_blank" rel="noopener">
        <i class="fab fa-whatsapp fa-lg"></i> Prévenir l'équipe sur WhatsApp
      </a>

      <div class="mr-liens">
        <a href="{{ route('portail.inscription') }}">Créer mon compte portail</a>
        <a href="{{ route('abonnements.service', \App\Models\Plan::segments()[$commande->service]) }}">
          Revoir les offres
        </a>
      </div>

    </div>
  </section>
</div>
@endsection
