@extends('layouts.public')

@section('title', 'Demander votre Agent IA | GOVIBE Innovation Hub')
@section('description', "Décrivez votre besoin et GOVIBE conçoit l'Agent IA adapté à votre entreprise : service client, WhatsApp, réservations, ventes et opérations.")

@section('head')
<style>
  .dm-hero {
    background: linear-gradient(135deg, #080002 0%, #1a0004 55%, #050505 100%);
    padding: 86px 1.5rem 46px; text-align: center;
  }
  .dm-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.9rem, 5vw, 2.8rem);
    color: #fff; margin: 0 0 .7rem; letter-spacing: .02em;
  }
  .dm-hero h1 span { color: #DC2626; }
  .dm-hero p { color: rgba(255,255,255,.72); max-width: 560px; margin: 0 auto; line-height: 1.7; font-size: .97rem; }

  .dm-sect { background: #f8fafc; padding: 44px 1.2rem 76px; }
  .dm-wrap {
    max-width: 1020px; margin: 0 auto;
    display: grid; grid-template-columns: 1fr 330px; gap: 1.5rem; align-items: start;
  }

  .dm-bloc { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; margin-bottom: 1.1rem; }
  .dm-bloc-tete {
    padding: 1.1rem 1.5rem; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: .6rem;
  }
  .dm-bloc-tete i { color: #DC2626; }
  .dm-bloc-tete h2 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1rem;
    color: #0f172a; margin: 0; letter-spacing: .03em;
  }
  .dm-bloc-corps { padding: 1.4rem 1.5rem; }

  .dm-champ { margin-bottom: 1.1rem; }
  .dm-champ:last-child { margin-bottom: 0; }
  .dm-champ label {
    display: block; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: .8rem; letter-spacing: .05em; text-transform: uppercase;
    color: #334155; margin-bottom: .4rem;
  }
  .dm-aide {
    display: block; font-family: 'DM Sans', sans-serif; text-transform: none;
    letter-spacing: 0; font-size: .77rem; color: #94a3b8; margin-top: .12rem;
  }
  .dm-req { color: #DC2626; }
  /* 16px : en dessous, iOS zoome au focus. */
  .dm-champ input[type=text], .dm-champ input[type=tel], .dm-champ input[type=email],
  .dm-champ input[type=url], .dm-champ select, .dm-champ textarea {
    width: 100%; font-size: 16px; padding: .72rem .9rem; border: 1px solid #d1d5db;
    border-radius: 11px; background: #fff; color: #0f172a; font-family: inherit;
  }
  .dm-champ input:focus, .dm-champ select:focus, .dm-champ textarea:focus {
    outline: none; border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220,38,38,.12);
  }
  .dm-champ textarea { min-height: 92px; resize: vertical; }
  .dm-duo { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }

  /* Pastilles : plus faciles à viser au pouce qu'une case de 13px. */
  .dm-pastilles { display: flex; flex-wrap: wrap; gap: .5rem; }
  .dm-pastille { position: relative; }
  .dm-pastille input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .dm-pastille span {
    display: inline-block; border: 1px solid #d1d5db; border-radius: 50px;
    padding: .5rem 1rem; font-size: .86rem; color: #475569; cursor: pointer;
    background: #fff; user-select: none;
  }
  .dm-pastille input:checked + span {
    border-color: #DC2626; background: #fef2f2; color: #991b1b; font-weight: 600;
  }
  .dm-pastille input:focus-visible + span { box-shadow: 0 0 0 3px rgba(220,38,38,.2); }

  /* Choix de l'agent : cartes radio */
  .dm-agents { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
  .dm-agent { position: relative; }
  .dm-agent input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .dm-agent span {
    display: flex; align-items: center; gap: .6rem; height: 100%;
    border: 1px solid #d1d5db; border-radius: 12px; padding: .7rem .85rem;
    font-size: .87rem; color: #334155; cursor: pointer; background: #fff; line-height: 1.35;
  }
  .dm-agent span i { color: #94a3b8; flex-shrink: 0; }
  .dm-agent input:checked + span {
    border-color: #DC2626; background: #fef2f2; color: #991b1b; font-weight: 600;
  }
  .dm-agent input:checked + span i { color: #DC2626; }
  .dm-agent input:focus-visible + span { box-shadow: 0 0 0 3px rgba(220,38,38,.2); }

  /* Moyens de paiement */
  .dm-moyens { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
  .dm-moyen { position: relative; }
  .dm-moyen input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .dm-moyen span {
    display: flex; align-items: center; gap: .55rem;
    border: 1px solid #d1d5db; border-radius: 12px; padding: .7rem .85rem;
    font-size: .87rem; color: #334155; cursor: pointer; background: #fff;
  }
  .dm-moyen img { width: 24px; height: 24px; object-fit: contain; }
  .dm-moyen-init {
    width: 24px; height: 24px; border-radius: 6px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 700; color: #64748b; flex-shrink: 0;
  }
  .dm-moyen input:checked + span { border-color: #DC2626; background: #fef2f2; color: #991b1b; font-weight: 600; }

  /* Récapitulatif */
  .dm-recap { position: sticky; top: 90px; }
  .dm-recap-carte { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; overflow: hidden; }
  .dm-recap-tete {
    background: linear-gradient(135deg, #1a0004, #0a0000); padding: 1.2rem 1.3rem;
  }
  .dm-recap-tete span {
    display: block; font-size: .68rem; letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.45); margin-bottom: .25rem;
  }
  .dm-recap-tete strong {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1.05rem;
    color: #fff; letter-spacing: .02em; line-height: 1.3;
  }
  .dm-recap-corps { padding: 1.2rem 1.3rem; }
  .dm-ligne {
    display: flex; justify-content: space-between; gap: .8rem;
    font-size: .87rem; padding: .42rem 0;
  }
  .dm-ligne + .dm-ligne { border-top: 1px solid #f8fafc; }
  .dm-ligne span { color: #64748b; }
  .dm-ligne strong { color: #0f172a; text-align: right; }
  .dm-total {
    display: flex; justify-content: space-between; align-items: baseline; gap: .8rem;
    margin-top: .7rem; padding-top: .8rem; border-top: 2px solid #f1f5f9;
  }
  .dm-total span { font-family: 'Anton', sans-serif; font-size: .84rem; color: #334155; letter-spacing: .04em; }
  .dm-total strong { font-family: 'Anton', sans-serif; font-size: 1.3rem; color: #DC2626; }
  .dm-recap-note {
    background: #f8fafc; border-top: 1px solid #f1f5f9;
    padding: .9rem 1.3rem; font-size: .78rem; color: #64748b; line-height: 1.6;
  }
  .dm-envoyer {
    width: 100%; background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    border: none; border-radius: 50px; padding: .95rem 1.4rem; cursor: pointer;
    font-family: 'Anton', sans-serif; font-size: 1rem; letter-spacing: .045em; margin-top: 1rem;
  }
  .dm-envoyer:hover { opacity: .93; }
  .dm-envoyer:disabled { opacity: .6; cursor: default; }

  .dm-erreurs {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
    padding: .95rem 1.15rem; margin-bottom: 1.2rem; color: #991b1b; font-size: .87rem;
  }
  .dm-erreurs ul { margin: .4rem 0 0; padding-left: 1.1rem; }
  .dm-retour { display: inline-block; margin-top: 1.1rem; color: #64748b; font-size: .87rem; text-decoration: none; }
  .dm-retour:hover { color: #DC2626; }

  @media (max-width: 900px) {
    .dm-wrap { grid-template-columns: 1fr; }
    .dm-recap { position: static; }
    /* Le récapitulatif passe avant le bouton, jamais après le formulaire. */
    .dm-recap { order: -1; }
  }
  @media (max-width: 560px) {
    .dm-duo, .dm-agents, .dm-moyens { grid-template-columns: 1fr; }
    .dm-bloc-corps { padding: 1.1rem; }
    .dm-hero { padding: 66px 1.1rem 38px; }
  }
</style>
@endsection

@section('content')

<section class="dm-hero">
  <h1>Demander votre <span>Agent IA</span></h1>
  <p>Dites-nous ce que votre agent doit savoir faire. GOVIBE revient vers vous avec le devis et le calendrier de mise en service.</p>
</section>

<section class="dm-sect">
  <form method="POST" action="{{ route('agents-ia.store') }}" class="dm-wrap" id="dmForm">
    @csrf

    <div>
      @if ($errors->any())
        <div class="dm-erreurs">
          <strong>Vérifiez ces points :</strong>
          <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif

      <div class="dm-bloc">
        <div class="dm-bloc-tete"><i class="fas fa-robot"></i><h2>Agent souhaité</h2></div>
        <div class="dm-bloc-corps">
          <div class="dm-agents">
            @foreach ($agents as $a)
              <label class="dm-agent">
                <input type="radio" name="agent" value="{{ $a->slug }}"
                       data-nom="{{ $a->nom }}"
                       data-installation="{{ $a->sur_devis ? '' : $a->prix_installation }}"
                       data-mensuel="{{ $a->sur_devis ? '' : $a->prix_mensuel }}"
                       data-devise="{{ $a->devise }}"
                       data-devis="{{ $a->sur_devis ? '1' : '0' }}"
                       @checked(old('agent', $choisi?->slug) === $a->slug) required>
                <span><i class="fas {{ $a->icone }}"></i> {{ $a->nom }}</span>
              </label>
            @endforeach
          </div>
        </div>
      </div>

      <div class="dm-bloc">
        <div class="dm-bloc-tete"><i class="fas fa-building"></i><h2>Votre entreprise</h2></div>
        <div class="dm-bloc-corps">
          <div class="dm-duo">
            <div class="dm-champ">
              <label for="entreprise">Nom de l'entreprise <span class="dm-req">*</span></label>
              <input type="text" name="entreprise" id="entreprise" value="{{ old('entreprise') }}" required maxlength="200">
            </div>
            <div class="dm-champ">
              <label for="responsable">Nom du responsable <span class="dm-req">*</span></label>
              <input type="text" name="responsable" id="responsable" value="{{ old('responsable') }}" required maxlength="150">
            </div>
          </div>
          <div class="dm-duo">
            <div class="dm-champ">
              <label for="email">Email professionnel <span class="dm-req">*</span></label>
              <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="190">
            </div>
            <div class="dm-champ">
              <label for="telephone">Téléphone / WhatsApp <span class="dm-req">*</span></label>
              <input type="tel" name="telephone" id="telephone" value="{{ old('telephone') }}" required maxlength="40" placeholder="+509 ...">
            </div>
          </div>
          <div class="dm-duo">
            <div class="dm-champ">
              <label for="secteur">Secteur d'activité</label>
              <input type="text" name="secteur" id="secteur" value="{{ old('secteur') }}" maxlength="80">
            </div>
            <div class="dm-champ">
              <label for="pays">Pays</label>
              <input type="text" name="pays" id="pays" value="{{ old('pays', 'Haïti') }}" maxlength="80">
            </div>
          </div>
          <div class="dm-duo">
            <div class="dm-champ">
              <label for="ville">Ville</label>
              <input type="text" name="ville" id="ville" value="{{ old('ville') }}" maxlength="120">
            </div>
            <div class="dm-champ">
              <label for="site_web">Site web <span class="dm-aide">Facultatif</span></label>
              <input type="text" name="site_web" id="site_web" value="{{ old('site_web') }}" maxlength="255" placeholder="https://">
            </div>
          </div>
        </div>
      </div>

      <div class="dm-bloc">
        <div class="dm-bloc-tete"><i class="fas fa-bullseye"></i><h2>Votre besoin</h2></div>
        <div class="dm-bloc-corps">
          <div class="dm-champ">
            <label for="objectifs">Objectifs principaux</label>
            <textarea name="objectifs" id="objectifs" maxlength="2000"
              placeholder="Ce que vous attendez de l'agent : moins d'appels manqués, plus de réservations, réponses la nuit...">{{ old('objectifs') }}</textarea>
          </div>
          <div class="dm-champ">
            <label for="a_automatiser">Que souhaitez-vous automatiser ?</label>
            <textarea name="a_automatiser" id="a_automatiser" maxlength="2000"
              placeholder="Les tâches que votre équipe répète tous les jours.">{{ old('a_automatiser') }}</textarea>
          </div>
          <div class="dm-duo">
            <div class="dm-champ">
              <label for="volume_conversations">Conversations par mois</label>
              <select name="volume_conversations" id="volume_conversations">
                <option value="">— Sélectionner —</option>
                @foreach (\App\Models\AgentIa::volumesConversations() as $v => $l)
                  <option value="{{ $v }}" @selected(old('volume_conversations') === $v)>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="dm-champ">
              <label for="langues">Langues nécessaires</label>
              <input type="text" name="langues" id="langues" value="{{ old('langues', 'Créole, Français') }}" maxlength="120">
            </div>
          </div>
          <div class="dm-champ">
            <label>Canal souhaité</label>
            <div class="dm-pastilles">
              @foreach (\App\Models\AgentIa::canauxDisponibles() as $v => $l)
                <label class="dm-pastille">
                  <input type="radio" name="canal" value="{{ $v }}" @checked(old('canal') === $v)>
                  <span>{{ $l }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="dm-bloc">
        <div class="dm-bloc-tete"><i class="fas fa-plug"></i><h2>Intégrations</h2></div>
        <div class="dm-bloc-corps">
          <div class="dm-champ">
            <label>De quoi l'agent doit-il se connecter ?
              <span class="dm-aide">Plusieurs choix possibles</span>
            </label>
            <div class="dm-pastilles">
              @foreach (\App\Models\AgentIa::integrationsDisponibles() as $v => $l)
                <label class="dm-pastille">
                  <input type="checkbox" name="integrations[]" value="{{ $v }}"
                         @checked(in_array($v, old('integrations', []), true))>
                  <span>{{ $l }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div class="dm-champ">
            <label for="message">Décrivez votre besoin ou votre problème</label>
            <textarea name="message" id="message" maxlength="3000">{{ old('message') }}</textarea>
          </div>
        </div>
      </div>

      <div class="dm-bloc">
        <div class="dm-bloc-tete"><i class="fas fa-credit-card"></i><h2>Choisissez votre mode de paiement</h2></div>
        <div class="dm-bloc-corps">
          @if ($passerelles->isEmpty())
            <p style="color:#64748b;font-size:.88rem;line-height:1.7;margin:0">
              L'équipe GOVIBE vous communiquera les modalités de paiement en réponse à votre demande.
            </p>
          @else
            <div class="dm-moyens">
              @foreach ($passerelles as $p)
                <label class="dm-moyen">
                  <input type="radio" name="moyen_paiement" value="{{ $p->code }}" @checked(old('moyen_paiement') === $p->code)>
                  <span>
                    @if ($p->logo_url)
                      <img src="{{ $p->logo_url }}" alt="" loading="lazy">
                    @else
                      <span class="dm-moyen-init">{{ $p->initiales }}</span>
                    @endif
                    {{ $p->nom }}
                  </span>
                </label>
              @endforeach
              <label class="dm-moyen">
                <input type="radio" name="moyen_paiement" value="" @checked(old('moyen_paiement') === '')>
                <span><span class="dm-moyen-init"><i class="fas fa-ellipsis"></i></span> Autre — à convenir</span>
              </label>
            </div>
            <p style="color:#94a3b8;font-size:.79rem;line-height:1.6;margin:.9rem 0 0">
              Le paiement se règle hors ligne. Après l'envoi, vous recevez les coordonnées
              du moyen choisi et un lien pour transmettre votre preuve de paiement.
            </p>
          @endif
        </div>
      </div>
    </div>

    {{-- Récapitulatif : ce que le client commande et ce qu'il règle. --}}
    <aside class="dm-recap">
      <div class="dm-recap-carte">
        <div class="dm-recap-tete">
          <span>Votre commande</span>
          <strong id="dmNom">{{ $choisi?->nom ?? 'Choisissez un agent' }}</strong>
        </div>
        <div class="dm-recap-corps">
          <div class="dm-ligne">
            <span>Installation et configuration</span>
            <strong id="dmInstallation">—</strong>
          </div>
          <div class="dm-ligne">
            <span>Service IA mensuel</span>
            <strong id="dmMensuel">—</strong>
          </div>
          <div class="dm-total">
            <span>À régler maintenant</span>
            <strong id="dmTotal">—</strong>
          </div>
          <button type="submit" class="dm-envoyer" id="dmBouton">Payer et demander mon Agent</button>
        </div>
        <p class="dm-recap-note" id="dmNote">
          Le prix final peut varier selon les fonctionnalités, les intégrations,
          le volume d'utilisation et le niveau de personnalisation.
          Le service mensuel démarre à la mise en ligne de l'agent.
        </p>
      </div>
      <a href="{{ route('agents-ia.index') }}" class="dm-retour">
        <i class="fas fa-arrow-left"></i> Revoir les agents
      </a>
    </aside>
  </form>
</section>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var form   = document.getElementById('dmForm');
  var bouton = document.getElementById('dmBouton');
  var nom    = document.getElementById('dmNom');
  var inst   = document.getElementById('dmInstallation');
  var mens   = document.getElementById('dmMensuel');
  var total  = document.getElementById('dmTotal');
  var note   = document.getElementById('dmNote');
  if (!form) return;

  var noteBase = note.textContent;

  function montant(v, devise) {
    var n = parseFloat(v);
    if (isNaN(n)) return null;
    var s = Number.isInteger(n) ? n.toLocaleString('fr-FR')
                                : n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    return s + ' ' + devise;
  }

  function rafraichir() {
    var choisi = form.querySelector('input[name="agent"]:checked');
    if (!choisi) return;

    var d = choisi.dataset;
    nom.textContent = d.nom;

    if (d.devis === '1') {
      // Sur devis : afficher un total inventé serait un engagement de prix.
      inst.textContent  = 'Sur devis';
      mens.textContent  = 'Sur devis';
      total.textContent = 'Sur devis';
      bouton.textContent = 'Demander mon devis';
      note.textContent = "GOVIBE étudie votre besoin et vous transmet un devis détaillé avant tout paiement.";
      return;
    }

    var i = montant(d.installation, d.devise);
    var m = montant(d.mensuel, d.devise);

    inst.textContent  = i || '—';
    mens.textContent  = m ? m + ' / mois' : '—';
    // Le mensuel démarre à la mise en ligne : il n'entre pas dans ce total.
    total.textContent = i || '—';
    bouton.textContent = 'Payer et demander mon Agent';
    note.textContent = noteBase;
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'agent') rafraichir();
  });

  form.addEventListener('submit', function () {
    bouton.disabled = true;
    bouton.textContent = 'Envoi en cours...';
  });

  rafraichir();
})();
</script>

@endsection
