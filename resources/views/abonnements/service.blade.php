@extends('layouts.public')

@section('title', $presentation['titre'].' | GOVIBE Innovation Hub')
@section('description', $presentation['accroche'])

@section('head')
<style>
  .ab { --ab-rouge:#DC2626; --ab-sombre:#991b1b; --ab-encre:#172033;
        --ab-gris:#667085; --ab-fond:#f7f8fa; --ab-trait:#e4e7ec; }

  .ab-hero { background:linear-gradient(135deg,var(--ab-sombre) 0%,var(--ab-rouge) 100%); color:#fff; }
  .ab-hero-in { max-width:1040px; margin:auto; padding:56px 16px 48px; }
  .ab-fil { font-size:12px; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.7); margin:0 0 12px; }
  .ab-hero h1 {
    font-family:'Anton',sans-serif; font-weight:400; letter-spacing:.5px; margin:0;
    font-size:clamp(30px,6vw,50px); line-height:1.06; text-wrap:balance;
  }
  .ab-hero p { margin:14px 0 0; max-width:58ch; font-size:16px; line-height:1.65; color:rgba(255,255,255,.9); }
  .ab-hero-liens { display:flex; flex-wrap:wrap; gap:10px; margin-top:24px; }
  .ab-hero-liens a {
    display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:11px 18px;
    font-size:14px; font-weight:700; text-decoration:none; border:1px solid rgba(255,255,255,.35);
    color:#fff;
  }
  .ab-hero-liens a.ab-plein { background:#fff; color:var(--ab-sombre); border-color:#fff; }

  .ab-sect { background:var(--ab-fond); padding:40px 0; }
  .ab-wrap { max-width:1040px; margin:auto; padding:0 16px; }
  .ab-titre2 { font-size:24px; font-weight:800; color:var(--ab-encre); margin:0 0 6px; text-wrap:balance; }
  .ab-sous2 { color:var(--ab-gris); font-size:14px; line-height:1.6; margin:0 0 22px; max-width:62ch; }

  /* ── Bascule mensuel / annuel ── */
  .ab-bascule { display:inline-flex; background:#fff; border:1px solid var(--ab-trait); border-radius:999px; padding:4px; gap:2px; margin-bottom:22px; }
  .ab-bascule button {
    border:0; background:transparent; cursor:pointer; font:inherit; font-size:13px; font-weight:700;
    color:var(--ab-gris); padding:8px 18px; border-radius:999px;
  }
  .ab-bascule button[aria-pressed="true"] { background:var(--ab-rouge); color:#fff; }

  /* ── Offres ── */
  .ab-grille { display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); align-items:start; }
  .ab-offre {
    background:#fff; border:1px solid var(--ab-trait); border-radius:14px; padding:22px;
    display:flex; flex-direction:column; gap:14px; height:100%;
  }
  .ab-offre.ab-vedette { border-color:var(--ab-rouge); box-shadow:0 10px 30px rgba(220,38,38,.1); }
  .ab-etiquette {
    align-self:flex-start; background:var(--ab-rouge); color:#fff; font-size:11px; font-weight:800;
    letter-spacing:.1em; text-transform:uppercase; border-radius:999px; padding:4px 10px;
  }
  .ab-offre h3 { margin:0; font-size:19px; font-weight:800; color:var(--ab-encre); }
  .ab-offre-desc { margin:0; font-size:13.5px; line-height:1.6; color:var(--ab-gris); }
  .ab-prix { display:flex; align-items:baseline; gap:6px; flex-wrap:wrap; }
  .ab-prix strong { font-family:'Anton',sans-serif; font-weight:400; font-size:34px; color:var(--ab-encre); letter-spacing:.5px; }
  .ab-prix span { font-size:13px; color:var(--ab-gris); font-weight:600; }
  .ab-prix-note { font-size:12px; color:var(--ab-gris); margin:-8px 0 0; }
  .ab-essai { font-size:12.5px; font-weight:700; color:#047857; background:#ecfdf5; border-radius:8px; padding:6px 10px; }
  .ab-liste { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
  .ab-liste li { display:flex; gap:9px; font-size:13.5px; line-height:1.5; color:var(--ab-encre); }
  .ab-liste i { color:var(--ab-rouge); margin-top:3px; font-size:12px; flex:0 0 auto; }
  .ab-quotas { display:flex; flex-wrap:wrap; gap:6px; }
  .ab-quota { font-size:11.5px; font-weight:700; color:var(--ab-gris); background:var(--ab-fond); border:1px solid var(--ab-trait); border-radius:7px; padding:4px 8px; }
  .ab-cta {
    margin-top:auto; display:block; text-align:center; text-decoration:none; border-radius:10px;
    padding:13px 16px; font-size:14.5px; font-weight:800;
    background:var(--ab-rouge); color:#fff;
  }
  .ab-cta.ab-devis { background:#fff; color:var(--ab-sombre); border:1px solid var(--ab-trait); }

  /* ── Aucune offre publiée ── */
  .ab-vide {
    background:#fff; border:1px dashed var(--ab-trait); border-radius:14px; padding:30px 22px; text-align:center;
  }
  .ab-vide h3 { margin:0 0 8px; font-size:18px; font-weight:800; color:var(--ab-encre); }
  .ab-vide p { margin:0 auto 18px; max-width:50ch; font-size:14px; line-height:1.65; color:var(--ab-gris); }

  /* ── Points forts ── */
  .ab-points { display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(215px,1fr)); }
  .ab-point { background:#fff; border:1px solid var(--ab-trait); border-radius:12px; padding:18px; }
  .ab-point i { color:var(--ab-rouge); font-size:19px; }
  .ab-point h4 { margin:10px 0 5px; font-size:15px; font-weight:800; color:var(--ab-encre); }
  .ab-point p { margin:0; font-size:13px; line-height:1.6; color:var(--ab-gris); }

  /* ── Les autres services ── */
  .ab-autres { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); }
  .ab-autre {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background:#fff; border:1px solid var(--ab-trait); border-radius:12px; padding:16px 18px;
    text-decoration:none; color:var(--ab-encre); font-weight:700; font-size:14.5px;
  }
  .ab-autre i { color:var(--ab-rouge); }

  .ab-note { font-size:12.5px; color:var(--ab-gris); line-height:1.65; margin:18px 0 0; }

  @media (max-width:560px) {
    .ab-hero-in { padding:40px 16px 34px; }
    .ab-offre { padding:18px; }
  }
</style>
@endsection

@section('content')
<div class="ab">

  <section class="ab-hero">
    <div class="ab-hero-in">
      <p class="ab-fil">GOVIBE · {{ $presentation['cle'] }}</p>
      <h1>{{ $presentation['titre'] }}</h1>
      <p>{{ $presentation['accroche'] }}</p>
      <div class="ab-hero-liens">
        <a href="#offres" class="ab-plein"><i class="fas fa-tags"></i> Voir les offres</a>
        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">
          <i class="fab fa-whatsapp"></i> Parler à l'équipe
        </a>
      </div>
    </div>
  </section>

  <section class="ab-sect" id="offres">
    <div class="ab-wrap">
      <h2 class="ab-titre2">Nos offres {{ mb_strtolower($libelle) }}</h2>

      @if ($plans->isEmpty())
        {{-- Aucun tarif inventé : tant que le catalogue n'est pas publié dans
             l'ERP, la page propose un devis plutôt qu'un prix faux. --}}
        <div class="ab-vide">
          <h3>Les offres sont en cours de publication</h3>
          <p>
            Nos tarifs {{ mb_strtolower($libelle) }} sont finalisés en ce moment.
            Écrivez-nous votre besoin sur WhatsApp : nous vous répondons avec une
            proposition chiffrée.
          </p>
          <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Bonjour GOVIBE, je veux un devis pour '.mb_strtolower($libelle).'.') }}"
             class="ab-cta" style="display:inline-block;max-width:320px" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> Demander un devis
          </a>
        </div>
      @else
        @php
          // Un plan qui n'a qu'un tarif annuel ne doit pas faire apparaître une
          // bascule qui afficherait « — » côté mensuel.
          $aMensuel = $plans->contains(fn ($p) => $p->prixPour('mensuel') !== null);
          $aAnnuel  = $plans->contains(fn ($p) => $p->prixPour('annuel') !== null);
          $formater = fn ($v) => floor($v) == $v
              ? number_format($v, 0, ',', ' ')
              : number_format($v, 2, ',', ' ');
        @endphp

        @if ($aMensuel && $aAnnuel)
          <p class="ab-sous2">Choisissez le rythme de facturation qui vous arrange.</p>
          <div class="ab-bascule" role="group" aria-label="Rythme de facturation">
            <button type="button" data-cycle="mensuel" aria-pressed="true">Mensuel</button>
            <button type="button" data-cycle="annuel" aria-pressed="false">Annuel</button>
          </div>
        @endif

        <div class="ab-grille">
          @foreach ($plans as $plan)
            @php
              $mensuel = $plan->prixPour('mensuel');
              $annuel = $plan->prixPour('annuel');
              // Le cycle d'ouverture est celui que le plan propose réellement.
              $cycleDefaut = $mensuel !== null ? 'mensuel' : 'annuel';
            @endphp

            <article class="ab-offre {{ $plan->mis_en_avant ? 'ab-vedette' : '' }}"
                     data-prix-mensuel="{{ $mensuel !== null ? $formater($mensuel) : '' }}"
                     data-prix-annuel="{{ $annuel !== null ? $formater($annuel) : '' }}"
                     data-devise="{{ $plan->devise }}"
                     data-url-mensuel="{{ $mensuel !== null ? route('abonnements.commande', [$plan, 'cycle' => 'mensuel']) : '' }}"
                     data-url-annuel="{{ $annuel !== null ? route('abonnements.commande', [$plan, 'cycle' => 'annuel']) : '' }}">

              @if ($plan->mis_en_avant)
                <span class="ab-etiquette">Le plus demandé</span>
              @endif

              <h3>{{ $plan->nom }}</h3>

              @if ($plan->description)
                <p class="ab-offre-desc">{{ $plan->description }}</p>
              @endif

              @if ($plan->sur_devis)
                <div class="ab-prix"><strong>Sur devis</strong></div>
                <p class="ab-prix-note">Le tarif dépend de ce que vous voulez faire.</p>
              @else
                <div class="ab-prix">
                  <strong data-prix>{{ $formater($cycleDefaut === 'mensuel' ? $mensuel : $annuel) }}</strong>
                  <span>{{ $plan->devise }} <span data-periode>/ {{ $cycleDefaut === 'mensuel' ? 'mois' : 'an' }}</span></span>
                </div>
                @if ((float) $plan->tca_taux > 0)
                  <p class="ab-prix-note">Hors taxe de {{ rtrim(rtrim(number_format((float) $plan->tca_taux, 2, ',', ' '), '0'), ',') }} %.</p>
                @endif
              @endif

              @if ($plan->essai_jours > 0)
                <p class="ab-essai">
                  <i class="fas fa-gift"></i>
                  {{ $plan->essai_jours }} jour{{ $plan->essai_jours > 1 ? 's' : '' }} d'essai avant la première facture
                </p>
              @endif

              @if (! empty($plan->fonctionnalites))
                <ul class="ab-liste">
                  @foreach ($plan->fonctionnalites as $fonction)
                    <li><i class="fas fa-check"></i> <span>{{ is_array($fonction) ? implode(' · ', $fonction) : $fonction }}</span></li>
                  @endforeach
                </ul>
              @endif

              @if (! empty($plan->quotas))
                <div class="ab-quotas">
                  @foreach ($plan->quotas as $nom => $valeur)
                    <span class="ab-quota">{{ is_int($nom) ? $valeur : $nom.' : '.(is_array($valeur) ? implode(', ', $valeur) : $valeur) }}</span>
                  @endforeach
                </div>
              @endif

              @if ($plan->sur_devis)
                <a class="ab-cta ab-devis" target="_blank" rel="noopener"
                   href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Bonjour GOVIBE, je veux un devis pour l\'offre '.$plan->nom.'.') }}">
                  <i class="fab fa-whatsapp"></i> Demander un devis
                </a>
              @else
                <a class="ab-cta" data-commander
                   href="{{ route('abonnements.commande', [$plan, 'cycle' => $cycleDefaut]) }}">
                  Commander cette offre
                </a>
              @endif
            </article>
          @endforeach
        </div>

        <p class="ab-note">
          Le total à régler s'affiche dans la devise du moyen de paiement que vous
          choisissez à l'étape suivante : en dollars pour une passerelle en dollars,
          en gourdes pour un paiement en gourdes.
        </p>
      @endif
    </div>
  </section>

  <section class="ab-sect" style="background:#fff">
    <div class="ab-wrap">
      <h2 class="ab-titre2">Ce qui est compris</h2>
      <p class="ab-sous2">Le service est tenu par notre équipe : vous n'avez ni serveur à acheter ni maintenance à suivre.</p>
      <div class="ab-points">
        @foreach ($presentation['points'] as $point)
          <div class="ab-point">
            <i class="fas {{ $point['icone'] }}"></i>
            <h4>{{ $point['titre'] }}</h4>
            <p>{{ $point['texte'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="ab-sect">
    <div class="ab-wrap">
      <h2 class="ab-titre2">Les autres services par abonnement</h2>
      <p class="ab-sous2">Un seul compte GOVIBE, une seule facturation, quel que soit le nombre de services.</p>
      <div class="ab-autres">
        @foreach (\App\Models\Plan::services() as $cle => $nom)
          @continue($cle === $service)
          <a class="ab-autre" href="{{ route('abonnements.service', \App\Models\Plan::segments()[$cle]) }}">
            <span>{{ $nom }}</span>
            <i class="fas fa-arrow-right"></i>
          </a>
        @endforeach
        <a class="ab-autre" href="{{ route('portail.connexion') }}">
          <span>Mon portail client</span>
          <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </section>

</div>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var bascule = document.querySelector('.ab-bascule');
  if (!bascule) return;

  var offres = Array.prototype.slice.call(document.querySelectorAll('.ab-offre'));

  function appliquer(cycle) {
    offres.forEach(function (offre) {
      var prix = offre.dataset['prix' + (cycle === 'annuel' ? 'Annuel' : 'Mensuel')];
      var url  = offre.dataset['url' + (cycle === 'annuel' ? 'Annuel' : 'Mensuel')];
      var cible = offre.querySelector('[data-prix]');
      var periode = offre.querySelector('[data-periode]');
      var lien = offre.querySelector('[data-commander]');

      // Une offre qui n'a pas ce tarif garde celui qu'elle affiche : effacer le
      // prix laisserait une carte muette au lieu d'une offre lisible.
      if (!prix || !cible) return;

      cible.textContent = prix;
      if (periode) periode.textContent = cycle === 'annuel' ? '/ an' : '/ mois';
      if (lien && url) lien.setAttribute('href', url);
    });
  }

  bascule.addEventListener('click', function (e) {
    var bouton = e.target.closest('button[data-cycle]');
    if (!bouton) return;

    bascule.querySelectorAll('button').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b === bouton));
    });

    appliquer(bouton.dataset.cycle);
  });
})();
</script>
@endsection
