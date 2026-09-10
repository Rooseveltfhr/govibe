<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('titre', 'Espace client') — GOVIBE</title>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --rouge:#DC2626; --rouge-fonce:#991b1b;
      --encre:#0f172a; --doux:#64748b; --gris:#94a3b8;
      --trait:#e5e7eb; --fond:#f8fafc; --surface:#fff;
      --ok:#047857; --ok-bg:#ecfdf5; --attente:#b45309; --attente-bg:#fffbeb;
    }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--fond); color:var(--encre);
           font-family:'DM Sans',system-ui,sans-serif; line-height:1.6; }
    a { color:var(--rouge-fonce); }

    .pf-barre {
      background:linear-gradient(135deg,#0a0000,#1a0004);
      padding:.9rem 1.3rem; display:flex; align-items:center;
      justify-content:space-between; gap:1rem; flex-wrap:wrap;
    }
    .pf-marque { display:flex; align-items:center; gap:.6rem; color:#fff;
                 font-family:'Anton',sans-serif; letter-spacing:.05em; font-size:1.15rem;
                 text-decoration:none; }
    .pf-marque i { color:var(--rouge); }
    .pf-nav { display:flex; gap:.3rem; flex-wrap:wrap; align-items:center; }
    .pf-nav a { color:rgba(255,255,255,.72); text-decoration:none; font-size:.88rem;
                padding:.4rem .8rem; border-radius:50px; }
    .pf-nav a:hover { background:rgba(255,255,255,.09); color:#fff; }
    .pf-nav a.actif { background:var(--rouge); color:#fff; }
    .pf-sortie { background:none; border:1px solid rgba(255,255,255,.25); color:rgba(255,255,255,.8);
                 font-family:inherit; font-size:.85rem; padding:.4rem .9rem; border-radius:50px; cursor:pointer; }
    .pf-sortie:hover { background:rgba(255,255,255,.1); color:#fff; }

    .pf-page { max-width:960px; margin:0 auto; padding:1.8rem 1.2rem 4rem; }
    .pf-titre { font-family:'Anton',sans-serif; font-weight:400; font-size:1.5rem;
                letter-spacing:.02em; margin:0 0 .3rem; }
    .pf-sous { color:var(--doux); font-size:.92rem; margin:0 0 1.6rem; }

    .pf-carte { background:var(--surface); border:1px solid var(--trait);
                border-radius:16px; overflow:hidden; }
    .pf-carte + .pf-carte { margin-top:1.1rem; }
    .pf-carte-tete { padding:1rem 1.3rem; border-bottom:1px solid var(--trait);
                     display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap; }
    .pf-carte-tete h2 { font-family:'Anton',sans-serif; font-weight:400; font-size:1rem;
                        letter-spacing:.04em; margin:0; }
    .pf-carte-corps { padding:1.3rem; }

    .pf-avis { border-radius:12px; padding:.9rem 1.2rem; font-size:.9rem; margin-bottom:1.2rem; }
    .pf-avis-ok { background:var(--ok-bg); border:1px solid #a7f3d0; color:var(--ok); }
    .pf-avis-attente { background:var(--attente-bg); border:1px solid #fde68a; color:var(--attente); }
    .pf-avis-erreur { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pf-avis ul { margin:.4rem 0 0; padding-left:1.1rem; }

    .pf-champ { margin-bottom:1.05rem; }
    .pf-champ label { display:block; font-family:'Anton',sans-serif; font-weight:400;
                      font-size:.78rem; letter-spacing:.06em; text-transform:uppercase;
                      color:#334155; margin-bottom:.35rem; }
    .pf-champ .aide { display:block; font-family:'DM Sans',sans-serif; text-transform:none;
                      letter-spacing:0; font-size:.77rem; color:var(--gris); margin-top:.1rem; }
    /* 16px : en dessous, iOS zoome au focus. */
    .pf-champ input { width:100%; font-size:16px; padding:.72rem .9rem;
                      border:1px solid #d1d5db; border-radius:11px; font-family:inherit;
                      background:#fff; color:var(--encre); }
    .pf-champ input:focus { outline:none; border-color:var(--rouge);
                            box-shadow:0 0 0 3px rgba(220,38,38,.12); }
    .pf-bouton { width:100%; background:linear-gradient(135deg,var(--rouge),var(--rouge-fonce));
                 color:#fff; border:none; border-radius:50px; padding:.9rem 1.4rem;
                 font-family:'Anton',sans-serif; font-size:1rem; letter-spacing:.045em; cursor:pointer; }
    .pf-bouton:hover { opacity:.93; }
    .pf-liens { text-align:center; margin-top:1.1rem; font-size:.88rem; color:var(--doux); }

    .pf-auth { max-width:430px; margin:0 auto; padding:3rem 1.2rem 4rem; }
    .pf-auth-tete { text-align:center; margin-bottom:1.6rem; }
    .pf-auth-tete h1 { font-family:'Anton',sans-serif; font-weight:400; font-size:1.6rem;
                       letter-spacing:.02em; margin:0 0 .3rem; }
    .pf-auth-tete p { color:var(--doux); font-size:.92rem; margin:0; }

    .pf-vide { text-align:center; padding:2.6rem 1rem; color:var(--gris); }
    .pf-vide i { font-size:2.2rem; opacity:.3; display:block; margin-bottom:.7rem; }

    .pf-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.9rem; margin-bottom:1.3rem; }
    .pf-stat { background:var(--surface); border:1px solid var(--trait); border-radius:14px; padding:1.05rem 1.2rem; }
    .pf-stat span { display:block; font-size:.75rem; color:var(--gris); margin-bottom:.2rem; }
    .pf-stat strong { font-family:'Anton',sans-serif; font-weight:400; font-size:1.5rem; letter-spacing:.02em; }

    .pf-ligne { display:flex; align-items:center; gap:.9rem; padding:.85rem 1.3rem;
                border-bottom:1px solid var(--trait); }
    .pf-ligne:last-child { border-bottom:none; }
    .pf-ligne .ico { width:34px; height:34px; border-radius:10px; background:#fef2f2; color:var(--rouge);
                     display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .pf-ligne .txt { flex:1; min-width:0; }
    .pf-ligne .txt strong { display:block; font-size:.93rem; }
    .pf-ligne .txt small { color:var(--gris); font-size:.79rem; }
    .pf-etat { font-size:.74rem; font-weight:700; border-radius:6px; padding:.16rem .5rem; white-space:nowrap; }
    .e-actif { background:var(--ok-bg); color:var(--ok); }
    .e-en_cours { background:var(--attente-bg); color:var(--attente); }
    .e-termine { background:#f1f5f9; color:var(--doux); }

    table { width:100%; border-collapse:collapse; font-size:.89rem; }
    th, td { text-align:left; padding:.6rem 1.3rem; border-bottom:1px solid var(--trait); }
    th { font-size:.72rem; letter-spacing:.09em; text-transform:uppercase; color:var(--gris); }
    tbody tr:last-child td { border-bottom:none; }
    .num { font-variant-numeric:tabular-nums; text-align:right; }

    @media (max-width:640px) {
      .pf-stats { grid-template-columns:1fr; }
      .pf-page { padding:1.3rem 1rem 3rem; }
    }
  </style>
</head>
<body>

@auth('client')
<nav class="pf-barre">
  <a href="{{ route('portail.tableau-bord') }}" class="pf-marque">
    <i class="fas fa-bolt"></i> GOVIBE
  </a>
  <div class="pf-nav">
    <a href="{{ route('portail.tableau-bord') }}" class="{{ request()->routeIs('portail.tableau-bord') ? 'actif' : '' }}">Tableau de bord</a>
    <a href="{{ route('portail.services') }}" class="{{ request()->routeIs('portail.services') ? 'actif' : '' }}">Mes services</a>
    <a href="{{ route('portail.factures') }}" class="{{ request()->routeIs('portail.factures') ? 'actif' : '' }}">Factures</a>
    <form method="POST" action="{{ route('portail.deconnexion') }}" style="display:inline">
      @csrf
      <button type="submit" class="pf-sortie">Se déconnecter</button>
    </form>
  </div>
</nav>
@endauth

@yield('contenu')

</body>
</html>
