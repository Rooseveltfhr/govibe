@props([
    // Montant et libellé facultatifs : le composant sert aussi de page
    // « comment payer » sans commande rattachée.
    'montant' => null,
    'devise' => 'HTG',
    'reference' => null,
    'titre' => 'Choisissez votre moyen de paiement',
])

@php
    // Une passerelle sans coordonnées ni lien n'aide personne à payer.
    $passerelles = \App\Models\PasserellePaiement::actif()->get()
        ->reject->est_incomplete
        ->values();
@endphp

@if ($passerelles->isNotEmpty())
<div class="mp-bloc" data-mp>

    <div class="mp-entete">
        <h3 class="mp-titre">{{ $titre }}</h3>
        @if ($montant !== null)
            <div class="mp-montant">
                <span class="mp-montant-label">Montant à payer</span>
                <strong>{{ number_format((float) $montant, 2, ',', ' ') }} {{ $devise }}</strong>
            </div>
        @endif
    </div>

    @if ($reference)
        <p class="mp-ref">
            Indiquez la référence <code>{{ $reference }}</code> dans le motif du paiement.
        </p>
    @endif

    {{-- Sélection --}}
    <div class="mp-choix" role="tablist">
        @foreach ($passerelles as $p)
            {{-- Le premier onglet est actif côté serveur : sans JavaScript,
                 la page reste utilisable avec un moyen de paiement affiché. --}}
            <button type="button" role="tab"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    aria-controls="mp-fiche-{{ $p->id }}"
                    data-mp-onglet="{{ $p->id }}"
                    class="mp-onglet{{ $loop->first ? ' actif' : '' }}">
                <span class="mp-onglet-logo">
                    @if ($p->logo_url)
                        <img src="{{ $p->logo_url }}" alt="" loading="lazy">
                    @else
                        {{ $p->initiales }}
                    @endif
                </span>
                <span class="mp-onglet-nom">{{ $p->nom }}</span>
            </button>
        @endforeach
    </div>

    {{-- Détails --}}
    @foreach ($passerelles as $p)
        <div class="mp-fiche" id="mp-fiche-{{ $p->id }}"
             data-mp-fiche="{{ $p->id }}" role="tabpanel" @unless($loop->first) hidden @endunless>

            <div class="mp-fiche-tete">
                <span class="mp-fiche-logo">
                    @if ($p->logo_url)
                        <img src="{{ $p->logo_url }}" alt="{{ $p->nom }}" loading="lazy">
                    @else
                        {{ $p->initiales }}
                    @endif
                </span>
                <div>
                    <strong class="mp-fiche-nom">{{ $p->nom }}</strong>
                    <span class="mp-fiche-type">{{ $p->type_libelle }}@if ($p->reseau) &middot; {{ $p->reseau }}@endif</span>
                </div>
            </div>

            <div class="mp-corps">
                <div class="mp-infos">
                    @if ($p->titulaire)
                        <div class="mp-ligne">
                            <span class="mp-k">Au nom de</span>
                            <span class="mp-v">{{ $p->titulaire }}</span>
                        </div>
                    @endif

                    @if ($p->valeur_a_copier)
                        <div class="mp-ligne">
                            <span class="mp-k">{{ $p->type === 'crypto' ? 'Adresse' : 'Numéro' }}</span>
                            <span class="mp-v mp-copiable">
                                <code>{{ $p->valeur_a_copier }}</code>
                                <button type="button" class="mp-copier"
                                        data-valeur="{{ $p->valeur_a_copier }}">Copier</button>
                            </span>
                        </div>
                    @endif

                    @if ($p->instructions)
                        <p class="mp-instructions">{{ $p->instructions }}</p>
                    @endif

                    @if ($p->lien_paiement)
                        <a href="{{ $p->lien_paiement }}" target="_blank" rel="noopener" class="mp-lien">
                            Payer avec {{ $p->nom }}
                        </a>
                    @endif
                </div>

                @if ($p->qr_code_url)
                    <figure class="mp-qr">
                        <img src="{{ $p->qr_code_url }}" alt="QR code {{ $p->nom }}" loading="lazy">
                        <figcaption>Scannez pour payer</figcaption>
                    </figure>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mp-apres-bloc">
        <p class="mp-apres">
            Après le paiement, envoyez la capture d'écran : c'est ce qui confirme votre place.
        </p>
        <a href="{{ route('paiement.preuve', array_filter(['montant' => $montant, 'motif' => $reference])) }}" class="mp-preuve">
            <i class="fas fa-paper-plane"></i> Envoyer la preuve de paiement
        </a>
        <p class="mp-apres mp-apres-sec">
            Ou directement à <a href="https://wa.me/50933988754" target="_blank" rel="noopener">+509 3398-8754</a>
            ou <a href="mailto:contact@govibeht.com">contact@govibeht.com</a>.
        </p>
    </div>
</div>

<style>
  .mp-apres-bloc {
    margin-top: 1.4rem; padding-top: 1.3rem; border-top: 1px solid #f1f5f9; text-align: center;
  }
  .mp-preuve {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .04em; font-size: 1rem;
    padding: .82rem 1.7rem; border-radius: 50px; text-decoration: none; margin: .5rem 0 .7rem;
  }
  .mp-preuve:hover { opacity: .93; color: #fff; }
  .mp-apres-sec { font-size: .82rem; color: #94a3b8; }
  @media (max-width: 560px) { .mp-preuve { width: 100%; } }
  .mp-bloc {
    border: 1px solid #e5e7eb; border-radius: 18px; background: #fff;
    padding: 1.8rem; margin: 1.5rem 0;
  }
  .mp-entete {
    display: flex; justify-content: space-between; align-items: baseline;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 1.3rem;
  }
  .mp-titre {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1.25rem;
    color: #0f172a; margin: 0; letter-spacing: .02em;
  }
  .mp-montant { text-align: right; }
  .mp-montant-label {
    display: block; font-size: .7rem; letter-spacing: .14em;
    text-transform: uppercase; color: #94a3b8;
  }
  .mp-montant strong {
    font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: 1.5rem; color: #DC2626; font-variant-numeric: tabular-nums;
  }
  .mp-ref {
    background: #fef2f2; border: 1px solid rgba(220,38,38,.2);
    border-radius: 9px; padding: .7rem .9rem; font-size: .87rem;
    color: #7f1d1d; margin: 0 0 1.2rem;
  }
  .mp-ref code { font-weight: 700; }

  /* Sélection */
  .mp-choix {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr));
    gap: .6rem; margin-bottom: 1.5rem;
  }
  .mp-onglet {
    display: flex; flex-direction: column; align-items: center; gap: .5rem;
    padding: .9rem .6rem; border: 1.5px solid #e5e7eb; border-radius: 12px;
    background: #fff; cursor: pointer; transition: border-color .18s, background .18s;
    font-family: inherit;
  }
  .mp-onglet:hover { border-color: #cbd5e1; }
  .mp-onglet:focus-visible { outline: 2px solid #DC2626; outline-offset: 2px; }
  .mp-onglet.actif { border-color: #DC2626; background: #fef2f2; }
  .mp-onglet-logo {
    width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Anton', sans-serif; font-size: .95rem; color: #DC2626;
    overflow: hidden; flex-shrink: 0;
  }
  .mp-onglet-logo img { max-width: 36px; max-height: 36px; object-fit: contain; }
  .mp-onglet-nom {
    font-size: .78rem; font-weight: 600; color: #334155;
    text-align: center; line-height: 1.25;
  }

  /* Fiche */
  .mp-fiche { border-top: 1px solid #f1f5f9; padding-top: 1.4rem; }
  .mp-fiche-tete { display: flex; align-items: center; gap: .9rem; margin-bottom: 1.2rem; }
  .mp-fiche-logo {
    width: 52px; height: 52px; border-radius: 12px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Anton', sans-serif; font-size: 1.1rem; color: #DC2626;
    overflow: hidden; flex-shrink: 0;
  }
  .mp-fiche-logo img { max-width: 42px; max-height: 42px; object-fit: contain; }
  .mp-fiche-nom {
    display: block; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: 1.15rem; color: #0f172a; letter-spacing: .02em;
  }
  .mp-fiche-type { font-size: .78rem; color: #94a3b8; }

  .mp-corps { display: grid; grid-template-columns: 1fr auto; gap: 1.8rem; align-items: start; }
  .mp-infos { min-width: 0; }
  .mp-ligne {
    display: grid; grid-template-columns: 7rem 1fr; gap: .8rem;
    padding: .55rem 0; border-bottom: 1px solid #f8fafc; align-items: baseline;
  }
  .mp-k { font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; color: #94a3b8; }
  .mp-v { font-size: .92rem; color: #0f172a; font-weight: 600; min-width: 0; }
  .mp-copiable { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
  /* Une adresse crypto doit pouvoir se couper : sinon elle déborde. */
  .mp-copiable code {
    font-size: .84rem; background: #f1f5f9; border-radius: 5px;
    padding: .2rem .45rem; word-break: break-all; font-weight: 400;
  }
  .mp-copier {
    border: 1px solid #DC2626; color: #DC2626; background: #fff;
    font-size: .72rem; font-weight: 700; letter-spacing: .05em;
    padding: .28rem .7rem; border-radius: 50px; cursor: pointer;
    white-space: nowrap; font-family: inherit; transition: background .18s, color .18s;
  }
  .mp-copier:hover { background: #DC2626; color: #fff; }
  .mp-copier:focus-visible { outline: 2px solid #DC2626; outline-offset: 2px; }
  .mp-copier[data-copie="1"] { background: #16a34a; border-color: #16a34a; color: #fff; }

  .mp-instructions {
    font-size: .87rem; color: #64748b; line-height: 1.65;
    margin: .9rem 0 0; max-width: 52ch;
  }
  .mp-lien {
    display: inline-block; margin-top: 1.1rem;
    background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    font-family: 'Anton', sans-serif; font-weight: 400; letter-spacing: .04em;
    font-size: .95rem; padding: .7rem 1.5rem; border-radius: 50px;
    text-decoration: none; transition: opacity .18s;
  }
  .mp-lien:hover { opacity: .92; color: #fff; }

  .mp-qr { margin: 0; text-align: center; }
  .mp-qr img {
    width: 150px; height: 150px; object-fit: contain;
    border: 1px solid #e5e7eb; border-radius: 12px; padding: .5rem; background: #fff;
  }
  .mp-qr figcaption {
    font-size: .72rem; color: #94a3b8; margin-top: .5rem;
    letter-spacing: .06em; text-transform: uppercase;
  }

  .mp-apres {
    font-size: .82rem; color: #94a3b8; line-height: 1.6;
    margin: 1.6rem 0 0; padding-top: 1.1rem; border-top: 1px solid #f1f5f9;
  }
  .mp-apres a { color: #DC2626; font-weight: 600; }

  @media (max-width: 640px) {
    .mp-bloc { padding: 1.2rem; }
    .mp-corps { grid-template-columns: 1fr; gap: 1.3rem; }
    .mp-qr { justify-self: center; }
    .mp-ligne { grid-template-columns: 1fr; gap: .2rem; }
    .mp-entete { flex-direction: column; align-items: flex-start; }
    .mp-montant { text-align: left; }
  }
</style>

<script>
(function () {
  document.querySelectorAll('[data-mp]').forEach(function (bloc) {
    var onglets = bloc.querySelectorAll('[data-mp-onglet]');
    var fiches  = bloc.querySelectorAll('[data-mp-fiche]');

    onglets.forEach(function (onglet) {
      onglet.addEventListener('click', function () {
        var cible = onglet.dataset.mpOnglet;

        onglets.forEach(function (o) {
          var actif = o.dataset.mpOnglet === cible;
          o.classList.toggle('actif', actif);
          o.setAttribute('aria-selected', actif ? 'true' : 'false');
        });
        fiches.forEach(function (f) {
          f.hidden = f.dataset.mpFiche !== cible;
        });
      });
    });

    bloc.querySelectorAll('.mp-copier').forEach(function (bouton) {
      bouton.addEventListener('click', function () {
        var valeur = bouton.dataset.valeur;
        var fini = function () {
          var avant = bouton.textContent;
          bouton.textContent = 'Copié';
          bouton.dataset.copie = '1';
          setTimeout(function () {
            bouton.textContent = avant;
            delete bouton.dataset.copie;
          }, 1800);
        };

        // clipboard exige un contexte sécurisé ; hors HTTPS on retombe sur une
        // invite que l'utilisateur copie lui-même.
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(valeur).then(fini).catch(function () {
            window.prompt('Copiez cette valeur :', valeur);
          });
        } else {
          window.prompt('Copiez cette valeur :', valeur);
        }
      });
    });
  });
})();
</script>
@endif
