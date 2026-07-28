@extends('layouts.public')

@section('title', 'GOVIBE Innovation Hub — Haïti')
@section('description', 'GOVIBE – Écosystème d\'innovation en Haïti. Coworking, Startup Lab, Call Center, IA, Formation, Crédit Digital.')

@section('head')
<style>
  /* ===== HERO — ANTIGRAVITY ===== */
  #govibe-hero {
    position:relative; width:100vw; min-height:100vh;
    margin-left:calc(-50vw + 50%); margin-right:calc(-50vw + 50%);
    background:linear-gradient(135deg,#0a0000 0%,#1a0004 45%,#050505 100%);
    display:flex; align-items:center; overflow:hidden;
    padding:80px 2rem 60px; box-sizing:border-box;
  }
  #gv-ag-canvas { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:1; }
  .gv-fw { position:absolute; pointer-events:none; will-change:transform,opacity; user-select:none;
    font-family:'Barlow Condensed',sans-serif; font-weight:900; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }
  @keyframes gvAF {
    0%   { transform:translateY(0) translateX(0) rotate(var(--r0)) scale(1); opacity:var(--o0); }
    25%  { transform:translateY(var(--y25)) translateX(var(--x25)) rotate(var(--r25)) scale(var(--s25)); opacity:var(--o1); }
    50%  { transform:translateY(var(--y50)) translateX(var(--x50)) rotate(var(--r50)) scale(var(--s50)); opacity:var(--o1); }
    75%  { transform:translateY(var(--y75)) translateX(var(--x75)) rotate(var(--r75)) scale(var(--s75)); opacity:var(--o1); }
    100% { transform:translateY(0) translateX(0) rotate(var(--r0)) scale(1); opacity:var(--o0); }
  }
  .gv-hero-grid {
    position:absolute; inset:0; z-index:2;
    background-image:linear-gradient(rgba(220,38,38,.05) 1px,transparent 1px),
      linear-gradient(90deg,rgba(220,38,38,.05) 1px,transparent 1px);
    background-size:50px 50px; animation:gvGridMove 12s linear infinite; pointer-events:none;
  }
  @keyframes gvGridMove { from{background-position:0 0} to{background-position:50px 50px} }
  .gv-blob { position:absolute; border-radius:50%; filter:blur(80px); opacity:.2;
    animation:gvBlobFloat 20s ease-in-out infinite; pointer-events:none; z-index:2; }
  .gv-blob-1 { width:500px;height:500px;background:#DC2626;top:-100px;right:-100px;animation-delay:0s; }
  .gv-blob-2 { width:400px;height:400px;background:#10B981;bottom:-80px;left:-80px;animation-delay:-7s; }
  .gv-blob-3 { width:300px;height:300px;background:#991b1b;top:40%;right:15%;animation-delay:-14s; }
  @keyframes gvBlobFloat {
    0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-30px) scale(1.05)} 66%{transform:translate(-20px,20px) scale(.95)}
  }
  .gv-hero-content { position:relative; z-index:10; max-width:820px; width:100%; margin:0 auto; }
  .gv-badge {
    display:inline-flex; align-items:center; gap:.5rem;
    background:rgba(255,255,255,.08); backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.12); padding:.45rem 1.1rem;
    border-radius:50px; color:#fff; font-size:.88rem; font-weight:500;
    margin-bottom:1.8rem; animation:gvFadeUp .8s ease both;
  }
  .gv-badge-dot { width:7px;height:7px;background:#10B981;border-radius:50%;
    animation:gvDotBlink 2s ease-in-out infinite;flex-shrink:0; }
  .gv-hero-title {
    font-family:'Anton',sans-serif; font-size:clamp(2.4rem,7vw,5rem);
    line-height:1.05; color:#fff; margin:0 0 1.2rem;
    animation:gvFadeUp .8s .15s ease both;
  }
  .gv-title-grad {
    background:linear-gradient(135deg,#DC2626,#ff6b6b,#10B981);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    animation:gvHueShift 6s ease infinite;
  }
  @keyframes gvHueShift { 0%,100%{filter:hue-rotate(0deg)} 50%{filter:hue-rotate(20deg)} }
  .gv-typing-row {
    display:flex; align-items:center; gap:.5rem; margin:.5rem 0 1.5rem;
    font-size:clamp(1.1rem,3.5vw,1.9rem); animation:gvFadeUp .8s .25s ease both; flex-wrap:wrap;
  }
  .gv-typing-pre  { color:rgba(255,255,255,.6); font-weight:300; }
  .gv-typing-word { color:#DC2626; font-weight:700; text-shadow:0 0 25px rgba(220,38,38,.5); }
  .gv-typing-cur  { color:#DC2626; animation:gvCursorBlink 1s infinite; }
  @keyframes gvCursorBlink { 0%,100%{opacity:1} 50%{opacity:0} }
  .gv-hero-desc {
    font-size:clamp(.95rem,2vw,1.1rem); color:rgba(255,255,255,.75);
    max-width:560px; margin:0 0 2rem; line-height:1.75;
    animation:gvFadeUp .8s .35s ease both;
  }
  .gv-hero-desc strong { color:#10B981; }
  .gv-btns { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:3rem; animation:gvFadeUp .8s .45s ease both; }
  .gv-btn-primary {
    background:linear-gradient(135deg,#DC2626,#991b1b); color:#fff;
    padding:.85rem 1.8rem; border-radius:50px; font-weight:700; font-size:.95rem;
    display:inline-flex; align-items:center; gap:.4rem;
    transition:transform .3s,box-shadow .3s; white-space:nowrap;
  }
  .gv-btn-primary:hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(220,38,38,.45); color:#fff; }
  .gv-btn-secondary {
    background:rgba(255,255,255,.08); color:#fff; padding:.85rem 1.8rem;
    border-radius:50px; font-weight:600; font-size:.95rem;
    border:1px solid rgba(255,255,255,.2); backdrop-filter:blur(8px);
    display:inline-flex; align-items:center; gap:.4rem;
    transition:background .3s,transform .3s; white-space:nowrap;
  }
  .gv-btn-secondary:hover { background:rgba(255,255,255,.15); transform:translateY(-3px); color:#fff; }
  .gv-stats { display:flex; align-items:center; gap:2rem; flex-wrap:wrap; animation:gvFadeUp .8s .55s ease both; }
  .gv-stat { display:flex; flex-direction:column; }
  .gv-stat-n {
    font-family:'Barlow Condensed',sans-serif; font-size:2rem; font-weight:900;
    background:linear-gradient(135deg,#DC2626,#ff6b6b);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; line-height:1;
  }
  .gv-stat-l { font-size:.72rem; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:1.5px; margin-top:3px; }
  .gv-stat-div { width:1px; height:32px; background:rgba(255,255,255,.15); }
  @keyframes gvFadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
  @keyframes gvDotBlink { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.6);opacity:.5} }

  /* ===== ABOUT ===== */
  #about { position:relative; width:100%; min-height:100vh; background:#06060f; overflow:hidden; padding:70px 1.5rem 0; }
  .about-grid-bg {
    position:absolute; inset:0;
    background-image:linear-gradient(rgba(220,38,38,.04) 1px,transparent 1px),
      linear-gradient(90deg,rgba(220,38,38,.04) 1px,transparent 1px);
    background-size:50px 50px; animation:gridMove 12s linear infinite; pointer-events:none; z-index:0;
  }
  @keyframes gridMove { 0%{background-position:0 0} 100%{background-position:50px 50px} }
  .about-blobs { position:absolute; inset:0; pointer-events:none; overflow:hidden; z-index:0; }
  .ab1,.ab2 { position:absolute; border-radius:50%; filter:blur(80px); opacity:.1; animation:blobFloat 20s ease-in-out infinite; }
  .ab1 { width:400px;height:400px;background:#DC2626;top:-100px;left:-100px; }
  .ab2 { width:300px;height:300px;background:#10B981;bottom:-80px;right:-80px;animation-delay:-10s; }
  @keyframes blobFloat { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-40px) scale(1.08)} 66%{transform:translate(-20px,20px) scale(.95)} }
  .about-intro { position:relative; z-index:10; display:flex; flex-direction:column; align-items:center; width:100%; }
  .about-eyebrow { font-family:'Exo 2',sans-serif; font-size:.8rem; font-weight:700; letter-spacing:.3em; text-transform:uppercase; color:rgba(255,255,255,.35); margin-bottom:.5rem; text-align:center; }
  .about-heading { font-family:'Anton',sans-serif; font-size:clamp(.9rem,2.5vw,1.4rem); color:rgba(255,255,255,.55); letter-spacing:.1em; text-transform:uppercase; margin-bottom:40px; text-align:center; }
  .about-heading span { color:rgba(255,255,255,.85); }
  .logo3d-scene { position:relative; z-index:10; display:flex; flex-direction:column; align-items:center; gap:20px; padding-bottom:50px; width:100%; }
  .logo3d { display:flex; align-items:center; gap:2px; perspective:900px; transform-style:preserve-3d; flex-wrap:nowrap; }
  .l3 { font-family:'Orbitron',sans-serif; font-size:clamp(42px,11vw,140px); line-height:1; display:inline-flex; align-items:center; justify-content:center; position:relative; cursor:default; transform-style:preserve-3d; transition:transform .15s ease; animation:letterIn .9s cubic-bezier(.34,1.56,.64,1) both; }
  .l3-blue  { color:#2563EB; -webkit-text-stroke:2px #1e40af; text-shadow:1px 1px 0 #1e3a8a,2px 2px 0 #1e3a8a,3px 3px 0 #1e3a8a,4px 4px 0 #1e3a8a,12px 14px 28px rgba(0,0,0,.85),0 0 55px rgba(37,99,235,.45); }
  .l3-yellow{ color:#ffe800; -webkit-text-stroke:2px #8a7d00; text-shadow:1px 1px 0 #eed500,2px 2px 0 #ddc200,3px 3px 0 #ccaf00,4px 4px 0 #bb9c00,12px 14px 28px rgba(0,0,0,.85),0 0 55px rgba(255,232,0,.5); }
  .l3-green { color:#10B981; -webkit-text-stroke:2px #005c22; text-shadow:1px 1px 0 #00cc47,2px 2px 0 #00b83e,3px 3px 0 #00a435,4px 4px 0 #00902c,12px 14px 28px rgba(0,0,0,.85),0 0 55px rgba(16,185,129,.5); }
  @keyframes letterIn { 0%{opacity:0;transform:translateY(-40px) rotateX(60deg) scale(.7)} 100%{opacity:1;transform:translateY(0) rotateX(0deg) scale(1)} }
  .l3-icon { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
  .power-ring {
    width:53%;height:53%;border-radius:50%;
    background:radial-gradient(circle at 38% 33%,#ff7070,#DC2626);
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 0 0 3px #880000,0 5px 10px rgba(0,0,0,.7),0 0 35px rgba(239,68,68,1),0 0 70px rgba(239,68,68,.4);
    animation:pulsePwr 2s ease-in-out infinite;
  }
  @keyframes pulsePwr {
    0%,100%{box-shadow:0 0 0 3px #880000,0 5px 10px rgba(0,0,0,.7),0 0 35px rgba(239,68,68,1),0 0 70px rgba(239,68,68,.4)}
    50%{box-shadow:0 0 0 3px #880000,0 5px 10px rgba(0,0,0,.7),0 0 50px rgba(239,68,68,1),0 0 100px rgba(239,68,68,.6)}
  }
  .bolt-svg { width:68%;height:68%;filter:drop-shadow(0 0 6px rgba(255,255,200,1));animation:boltFlash 3.5s ease-in-out infinite; }
  @keyframes boltFlash { 0%,90%,100%{opacity:1} 93%,97%{opacity:.3} }
  .energy-bar-wrap { width:clamp(280px,80vw,800px);height:5px;background:rgba(255,255,255,.05);border-radius:10px;overflow:hidden;opacity:0;animation:fadeInEl .5s 1.1s ease forwards; }
  .energy-bar { height:100%;width:0;background:linear-gradient(90deg,#DC2626,#ffe800,#10B981,#ff6b6b,#a855f7,#DC2626);background-size:300% 100%;border-radius:10px;animation:barExpand 1s 1.1s ease forwards,colorFlow 2.5s 2.1s linear infinite;box-shadow:0 0 12px rgba(220,38,38,.7); }
  @keyframes barExpand{from{width:0}to{width:100%}}
  @keyframes colorFlow{from{background-position:0% 0}to{background-position:300% 0}}
  @keyframes fadeInEl{to{opacity:1}}
  .service-tags { display:flex;flex-wrap:wrap;justify-content:center;gap:8px;max-width:840px;width:100%;padding:0 1rem;opacity:0;animation:riseUp .7s 1.4s cubic-bezier(.34,1.3,.64,1) forwards; }
  .stag { display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:6px;font-family:'Exo 2',sans-serif;font-size:clamp(11px,1.6vw,14px);font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);backdrop-filter:blur(6px);cursor:default;transition:transform .2s,box-shadow .2s,border-color .2s;text-decoration:none; }
  .stag:hover{transform:translateY(-2px);border-color:var(--c);box-shadow:0 0 18px color-mix(in srgb,var(--c) 30%,transparent);}
  .sdot{width:8px;height:8px;border-radius:50%;background:var(--c);box-shadow:0 0 8px var(--c);animation:dotBlink 2.2s ease-in-out infinite;flex-shrink:0;}
  @keyframes dotBlink{0%,100%{opacity:1}50%{opacity:.3}}
  @keyframes riseUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
  .slogan-wrap{display:flex;flex-direction:column;align-items:center;gap:8px;opacity:0;animation:riseUp .8s 2.1s ease forwards;width:100%;padding:0 1rem;}
  .slogan-line{width:clamp(240px,60vw,650px);height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);}
  .slogan-txt{font-family:'Exo 2',sans-serif;font-size:clamp(11px,2.2vw,20px);font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.45);text-align:center;}
  .slogan-txt em{color:#ffe800;font-style:normal;text-shadow:0 0 18px rgba(255,232,0,.6);}
  .join-cta{display:flex;align-items:center;gap:12px;padding:12px 24px;border-radius:50px;border:1px solid rgba(220,38,38,.4);background:rgba(220,38,38,.07);cursor:pointer;opacity:0;animation:riseUp .6s 2.6s ease forwards;transition:transform .2s,box-shadow .2s,background .2s;text-decoration:none;white-space:nowrap;}
  .join-cta:hover{background:rgba(220,38,38,.15);box-shadow:0 0 30px rgba(220,38,38,.3);transform:translateY(-2px);}
  .jdot{width:10px;height:10px;border-radius:50%;background:#DC2626;box-shadow:0 0 10px #DC2626;animation:dotBlink 1.5s ease-in-out infinite;flex-shrink:0;}
  .jtxt{font-family:'Exo 2',sans-serif;font-size:clamp(12px,1.8vw,16px);font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.75);}
  .jtxt em{color:#DC2626;font-style:normal;text-shadow:0 0 14px rgba(220,38,38,.7);}
  .jarrow{color:#DC2626;font-size:20px;font-weight:900;animation:arrowBounce 1.5s ease-in-out infinite;}
  @keyframes arrowBounce{0%,100%{transform:translateX(0)}50%{transform:translateX(5px)}}
  .about-content-grid{position:relative;z-index:10;max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:start;padding:60px 2rem 80px;width:100%;}
  .about-text-col h2{font-family:'Anton',sans-serif;font-size:clamp(1.6rem,4vw,2.8rem);color:#fff;line-height:1.15;margin-bottom:1.2rem;}
  .about-text-col h2 span{color:#ffe800;}
  .about-text-col p{color:rgba(255,255,255,.65);line-height:1.8;font-size:1rem;margin-bottom:1.2rem;font-family:'Exo 2',sans-serif;}
  .about-bullets{list-style:none;display:flex;flex-direction:column;gap:.7rem;margin-top:1.5rem;}
  .about-bullets li{display:flex;align-items:flex-start;gap:.8rem;color:rgba(255,255,255,.75);font-size:.95rem;font-family:'Exo 2',sans-serif;}
  .about-bullets li i{color:#ffe800;flex-shrink:0;margin-top:2px;font-size:.8rem;}
  .about-cards-col{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
  .about-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1.3rem;transition:all .3s;text-decoration:none;display:block;}
  .about-card:hover{background:rgba(255,255,255,.08);border-color:rgba(220,38,38,.3);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.3);}
  .about-card i{font-size:2rem;margin-bottom:.6rem;display:block;color:#DC2626;}
  .about-card h4{font-family:'Anton',sans-serif;font-size:1rem;color:#fff;letter-spacing:.05em;margin-bottom:.4rem;}
  .about-card p{font-size:.82rem;color:rgba(255,255,255,.5);line-height:1.5;font-family:'Exo 2',sans-serif;}

  /* ===== LIGHT SECTIONS ===== */
  .gv-section-light { padding:80px 2rem; background:#fff; }
  .gv-section-gray  { padding:80px 2rem; background:linear-gradient(135deg,#f8fafc 0%,#fff 100%); }
  .gv-section-dark  { padding:80px 2rem; background:#0F172A; }

  /* Service cards */
  .service-card{background:#fff;border-radius:16px;padding:1.8rem;box-shadow:0 4px 20px rgba(0,0,0,.04);border:1px solid #f1f5f9;transition:all .3s;}
  .service-card:hover{box-shadow:0 20px 30px rgba(220,38,38,.08);border-color:rgba(220,38,38,.2);transform:translateY(-4px);}
  .service-card h3{font-family:'Anton',sans-serif;font-size:1.4rem;margin:.75rem 0 .5rem;color:#0f172a;}
  .service-card p{color:#64748b;font-size:.9rem;line-height:1.65;margin-bottom:1rem;}
  .svc-link{display:inline-flex;align-items:center;gap:.3rem;color:#DC2626;font-weight:700;font-size:.85rem;border:1px solid #DC2626;padding:.35rem 1rem;border-radius:50px;transition:all .25s;}
  .svc-link:hover{background:#DC2626;color:#fff;}

  /* Programme cards */
  .programme-card{background:#fff;border-radius:24px;padding:2.2rem;box-shadow:0 15px 35px rgba(0,0,0,.05);border:1px solid #f1f5f9;transition:all .3s;position:relative;overflow:hidden;}
  .programme-card:hover{transform:translateY(-6px);box-shadow:0 25px 40px rgba(220,38,38,.1);border-color:rgba(220,38,38,.2);}
  .programme-card h3{font-family:'Anton',sans-serif;font-size:1.8rem;color:#0f172a;margin:.5rem 0 1rem;}

  /* Digital cards */
  .service-digital{background:#fff;border-radius:20px;padding:1.8rem;box-shadow:0 10px 25px rgba(0,0,0,.04);border:1px solid #f1f5f9;transition:all .3s;text-align:center;}
  .service-digital:hover{transform:translateY(-6px);box-shadow:0 20px 35px rgba(220,38,38,.1);border-color:rgba(220,38,38,.2);}
  .service-digital h3{font-family:'Anton',sans-serif;font-size:1.2rem;margin:.6rem 0 .4rem;}

  /* Confiance cards */
  .confiance-card{background:#fff;border-radius:20px;padding:1.8rem;box-shadow:0 10px 25px rgba(0,0,0,.04);border:1px solid #f1f5f9;transition:all .3s;text-align:center;}
  .confiance-card:hover{transform:translateY(-5px);box-shadow:0 20px 30px rgba(220,38,38,.1);border-color:rgba(220,38,38,.2);}
  .confiance-card h3{font-family:'Anton',sans-serif;font-size:1.1rem;margin:.6rem 0 .3rem;}
  .temoignage-card{background:#fff;border-radius:24px;padding:2rem;box-shadow:0 15px 35px rgba(0,0,0,.05);border:1px solid #f1f5f9;transition:all .3s;}
  .temoignage-card:hover{transform:translateY(-5px);box-shadow:0 25px 40px rgba(220,38,38,.08);}

  /* Clients */
  .clients-grid{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:1.5rem;margin-top:2rem;}
  .client-logo{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem 1.8rem;transition:all .3s;min-width:140px;text-align:center;}
  .client-logo:hover{box-shadow:0 8px 24px rgba(220,38,38,.12);border-color:rgba(220,38,38,.25);transform:translateY(-2px);}
  .client-logo span{font-family:'Anton',sans-serif;font-size:.95rem;color:#475569;letter-spacing:.05em;}

  /* Pourquoi card */
  .pourquoi-card{padding:1.45rem;border:1px solid rgba(255,255,255,.05);border-radius:12px;text-decoration:none;display:block;transition:all .3s;color:inherit;}
  .pourquoi-card:hover{background:rgba(255,255,255,.05);border-color:rgba(220,38,38,.3);}
  .pourquoi-card h3{font-family:'Anton',sans-serif;font-size:1.15rem;color:#fff;margin:.5rem 0 .4rem;}

  /* Reservation form */
  #reservation input,#reservation select,#reservation textarea{
    background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
    border-radius:8px;padding:.72rem .95rem;color:#fff;width:100%;
    font-family:'DM Sans',sans-serif;font-size:.9rem;transition:border-color .2s;
  }
  #reservation input:focus,#reservation select:focus,#reservation textarea:focus{
    outline:none;border-color:#DC2626;background:rgba(220,38,38,.08);
  }
  #reservation select option{background:#1a0004;color:#fff;}
  #reservation label{display:block;font-size:.78rem;font-weight:600;color:rgba(255,255,255,.6);margin-bottom:.38rem;}

  /* ===== RESPONSIVE ===== */
  @media(max-width:768px){
    #govibe-hero{padding:70px 1.2rem 50px;min-height:100svh;padding-top:90px;}
    .gv-btns{flex-direction:column;width:100%;}
    .gv-btn-primary,.gv-btn-secondary{width:100%;text-align:center;justify-content:center;}
    .gv-stats{gap:1rem;}
    .gv-stat-div{display:none;}
    .gv-blob-1{width:250px;height:250px;}
    .gv-blob-2{width:200px;height:200px;}
    .gv-blob-3{display:none;}
    .about-content-grid{grid-template-columns:1fr;gap:2rem;padding:40px 1.5rem 60px;}
    .about-cards-col{grid-template-columns:1fr;}
    .logo3d{gap:0;}
    .logo3d-scene{gap:14px;padding-bottom:36px;}
    .svc-grid,.dig-grid{grid-template-columns:1fr!important;}
    .prog-grid,.conf-testi-grid,.reservation-grid{grid-template-columns:1fr!important;}
    .conf-stat-grid{grid-template-columns:repeat(2,1fr)!important;}
    .vision-grid{grid-template-columns:1fr!important;}
  }
  @media(max-width:576px){
    .about-intro{padding:50px 1rem 0;}
    .stag{padding:6px 9px;font-size:10px;}
    .conf-stat-grid{grid-template-columns:1fr!important;}
  }
  @media(max-width:380px){ .l3{font-size:36px;} }
</style>
@endsection

@section('content')

{{-- ===== HERO ===== --}}
<section id="govibe-hero">
  <div id="gv-ag-canvas"></div>
  <div class="gv-hero-grid"></div>
  <div class="gv-blob gv-blob-1"></div>
  <div class="gv-blob gv-blob-2"></div>
  <div class="gv-blob gv-blob-3"></div>
  <div class="gv-hero-content">
    <div class="gv-badge">
      <span class="gv-badge-dot"></span>
      <i class="fas fa-rocket"></i>
      <span data-i18n="hero_badge">Où l'Innovation, la Technologie &amp; les Idées Grandissent</span>
    </div>
    <h1 class="gv-hero-title">
      <span data-i18n="hero_title_1">GOVIBE – L'écosystème qui transforme</span><br>
      <span class="gv-title-grad" data-i18n="hero_title_2">vos idées et vos projets</span><br>
      <span data-i18n="hero_title_3">en succès</span>
    </h1>
    <div class="gv-typing-row">
      <span class="gv-typing-pre">:::</span>
      <span id="gvTypingWord" class="gv-typing-word">Entrepreneurs.</span>
      <span class="gv-typing-cur">|</span>
    </div>
    <p class="gv-hero-desc">
      <span data-i18n="hero_desc">Infrastructure Physique. Numérique. Financier. Intellectuel.</span>
      <strong data-i18n="hero_desc2">Un écosystème</strong>
      <span data-i18n="hero_desc3"> qui permet aux entrepreneurs, startups et organisations de se développer au-delà des limites.</span>
    </p>
    <div class="gv-btns">
      <a href="#programmes" class="gv-btn-primary">
        <i class="fas fa-chart-line"></i> <span data-i18n="hero_cta1">Explorer l'Écosystème</span> →
      </a>
      <a href="{{ route('coworking') }}" class="gv-btn-secondary">
        <i class="fas fa-building"></i> <span data-i18n="hero_cta2">Coworking Space</span>
      </a>
      <a href="#reservation" class="gv-btn-secondary">
        <i class="fas fa-calendar-alt"></i> <span data-i18n="hero_cta3">Réserver un Espace</span>
      </a>
    </div>
    <div class="gv-stats">
      <div class="gv-stat"><span class="gv-stat-n">50+</span><span class="gv-stat-l" data-i18n="stat_experts">Experts</span></div>
      <div class="gv-stat-div"></div>
      <div class="gv-stat"><span class="gv-stat-n">+1000</span><span class="gv-stat-l" data-i18n="stat_clients">Clients</span></div>
      <div class="gv-stat-div"></div>
      <div class="gv-stat"><span class="gv-stat-n">24/7</span><span class="gv-stat-l" data-i18n="stat_support">Support</span></div>
      <div class="gv-stat-div"></div>
      <div class="gv-stat"><span class="gv-stat-n">+9</span><span class="gv-stat-l" data-i18n="stat_countries">Pays</span></div>
    </div>
  </div>
</section>

{{-- ===== ABOUT ===== --}}
<section id="about">
  <div class="about-grid-bg"></div>
  <div class="about-blobs"><div class="ab1"></div><div class="ab2"></div></div>
  <div class="about-intro">
    <p class="about-eyebrow" data-i18n="about_eyebrow">À propos de GOVIBE</p>
    <h2 class="about-heading">Un <span>Écosystème</span>, des Possibilités Infinies</h2>
    <div class="logo3d-scene">
      <div class="logo3d" id="logo3d">
        <span class="l3 l3-blue" style="animation-delay:.00s">G</span>
        <span class="l3 l3-blue" style="animation-delay:.09s;position:relative">O
          <span class="l3-icon">
            <span class="power-ring">
              <svg class="bolt-svg" viewBox="0 0 24 24" fill="none">
                <path d="M6.5 5A8.5 8.5 0 1 0 17.5 5" stroke="white" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M13.5 2.5L8.8 13H13L10.5 22L18 8.5H13.5Z" fill="white"/>
              </svg>
            </span>
          </span>
        </span>
        <span class="l3 l3-yellow" style="animation-delay:.18s">V</span>
        <span class="l3 l3-green"  style="animation-delay:.27s">I</span>
        <span class="l3 l3-blue"   style="animation-delay:.36s">B</span>
        <span class="l3 l3-blue"   style="animation-delay:.45s">E</span>
      </div>
      <div class="energy-bar-wrap"><div class="energy-bar"></div></div>
      <div class="service-tags">
        <a href="{{ route('coworking') }}" class="stag" style="--c:#2563EB"><span class="sdot"></span><i class="fas fa-building"></i> Coworking Space</a>
        <a href="{{ route('startup-lab') }}" class="stag" style="--c:#ffe800"><span class="sdot"></span><i class="fas fa-chart-line"></i> Startup Lab</a>
        <a href="https://1207.3cx.cloud/supporttechnical" class="stag" style="--c:#10B981" target="_blank" rel="noopener"><span class="sdot"></span><i class="fas fa-headset"></i> Call Center</a>
        <a href="{{ route('startup-lab') }}#erp" class="stag" style="--c:#DC2626"><span class="sdot"></span><i class="fas fa-code"></i> ERP / CRM</a>
        <a href="{{ route('startup-lab') }}#ai" class="stag" style="--c:#a855f7"><span class="sdot"></span><i class="fas fa-brain"></i> Intelligence Artificielle</a>
        <a href="{{ route('about') }}" class="stag" style="--c:#00cfff"><span class="sdot"></span><i class="fas fa-globe"></i> Ecosystème</a>
        <a href="{{ route('academy') }}" class="stag" style="--c:#10B981"><span class="sdot"></span><i class="fas fa-graduation-cap"></i> GOVIBE Academy</a>
        <a href="{{ route('startup-lab') }}#saas" class="stag" style="--c:#ff8c00"><span class="sdot"></span><i class="fas fa-cloud"></i> SaaS Plateformes</a>
      </div>
      <div class="slogan-wrap">
        <div class="slogan-line"></div>
        <p class="slogan-txt">nous construisons des infrastructures pour votre <em>réussite</em></p>
        <div class="slogan-line"></div>
      </div>
      <a href="{{ route('inscription.create') }}" class="join-cta">
        <span class="jdot"></span>
        <span class="jtxt">Rejoignez notre écosystème dès <em>aujourd'hui</em></span>
        <span class="jarrow">→</span>
      </a>
    </div>
  </div>

  <div class="about-content-grid">
    <div class="about-text-col slide-up">
      <h2>Le principal hub d'innovation et de<br><span>technologie en Haïti.</span></h2>
      <p>Fondé en 2020 par Roosevelt Forestal, GOVIBE est bien plus qu'un espace de travail. C'est un écosystème d'innovation conçu pour donner aux entrepreneurs, aux professionnels et aux organisations les moyens de construire, grandir et réussir.</p>
      <p>Avec 13 employés à temps plein et 23 à temps partiel, nous servons plus de 1000 clients à travers Haïti, les États-Unis, le Canada et l'Afrique.</p>
      <ul class="about-bullets">
        <li><i class="fas fa-chevron-circle-right"></i> Enregistré aux USA (IRS — GOVIBE STARTUP LLC)</li>
        <li><i class="fas fa-chevron-circle-right"></i> Enregistré en Haïti (MCI — Ministère du Commerce)</li>
        <li><i class="fas fa-chevron-circle-right"></i> Modèle hybride : Physique + Numérique + Financier</li>
        <li><i class="fas fa-chevron-circle-right"></i> Outils IA et transformation numérique</li>
        <li><i class="fas fa-chevron-circle-right"></i> Standards internationaux, vision mondiale</li>
      </ul>
      <div style="margin-top:1.5rem;">
        <a href="{{ route('about') }}" class="gv-btn-prim"><i class="fas fa-info-circle"></i> En savoir plus →</a>
      </div>
    </div>
    <div class="about-cards-col slide-up delay-2">
      <a href="{{ route('about') }}#vision" class="about-card">
        <i class="fas fa-bullseye"></i><h4>Vision</h4>
        <p>Être le principal écosystème d'innovation dans la Caraïbe.</p>
      </a>
      <a href="{{ route('about') }}#mission" class="about-card">
        <i class="fas fa-bolt"></i><h4>Mission</h4>
        <p>Construire une infrastructure physique, numérique et financière.</p>
      </a>
      <a href="{{ route('about') }}#clients" class="about-card">
        <i class="fas fa-globe-americas"></i><h4>Impact</h4>
        <p>Delimart, Prestige, Sunrise Airways, Access Haiti et +1000 clients.</p>
      </a>
      <a href="{{ route('about') }}#team" class="about-card">
        <i class="fas fa-handshake"></i><h4>Communauté</h4>
        <p>36 experts dévoués au service de l'innovation haïtienne.</p>
      </a>
    </div>
  </div>
</section>

{{-- ===== CLIENTS ===== --}}
<section id="clients-section" style="padding:60px 2rem; background:#fff; border-top:1px solid #f1f5f9;">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:1rem;">
      <span class="gv-stag"><i class="fas fa-handshake"></i> NOS PARTENAIRES</span>
      <h2 class="gv-h2">Ils nous font <span style="color:#DC2626;">confiance</span></h2>
      <p class="gv-sub" style="margin:0 auto; text-align:center;">Haïti &middot; Canada &middot; USA &middot; Afrique</p>
    </div>
    <div class="clients-grid slide-up">
      <div class="client-logo"><span>Delimart Haiti</span></div>
      <div class="client-logo"><span>Prestige Bière</span></div>
      <div class="client-logo"><span>Sunrise Airways</span></div>
      <div class="client-logo"><span>Access Haiti</span></div>
      <div class="client-logo"><span>+1000 Clients</span></div>
    </div>
  </div>
</section>

{{-- ===== SERVICES ===== --}}
<section id="services" class="gv-section-light">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-bolt"></i> NOTRE ÉCOSYSTÈME</span>
      <h2 class="gv-h2">GOVIBE –
        <span style="background:linear-gradient(135deg,#DC2626,#10B981,#ff6b6b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Infrastructure d'Innovation</span><br>
        &amp; Solutions Digitales
      </h2>
      <p class="gv-sub" style="max-width:700px; margin:0 auto; text-align:center;">GOVIBE combine <strong>infrastructures physiques, solutions digitales, IA et SaaS</strong> pour accompagner entreprises et entrepreneurs.</p>
    </div>

    <div class="svc-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(290px,1fr)); gap:1.8rem; margin-bottom:3rem;">
      <div class="service-card slide-up">
        <i class="fas fa-building" style="font-size:2.2rem; color:#2563EB;"></i>
        <h3>GOVIBE Coworking Space</h3>
        <p>Espaces flexibles, bureaux privés, salles de conférence, domiciliation et internet haut débit à Port-au-Prince.</p>
        <a href="{{ route('coworking') }}" class="svc-link">Découvrir <i class="fas fa-arrow-right fa-xs"></i></a>
      </div>
      <div class="service-card slide-up delay-1">
        <i class="fas fa-headset" style="font-size:2.2rem; color:#10B981;"></i>
        <h3>GOVIBE Call Center</h3>
        <p>Réception d'appels, service client externalisé, support technique et secrétariat à distance 24/7.</p>
        <a href="https://1207.3cx.cloud/supporttechnical" class="svc-link" target="_blank" rel="noopener">Appeler <i class="fas fa-phone fa-xs"></i></a>
      </div>
      <div class="service-card slide-up delay-2">
        <i class="fas fa-code" style="font-size:2.2rem; color:#DC2626;"></i>
        <h3>GOVIBE Startup Lab</h3>
        <p>Développement web, mobile, ERP, CRM, IA, cybersécurité et solutions SaaS sur mesure pour votre entreprise.</p>
        <a href="{{ route('startup-lab') }}" class="svc-link">Explorer <i class="fas fa-arrow-right fa-xs"></i></a>
      </div>
      <div class="service-card slide-up">
        <i class="fas fa-robot" style="font-size:2.2rem; color:#a855f7;"></i>
        <h3>GOVIBE AI</h3>
        <p>Agents IA, chatbots intelligents, analyse prédictive, automatisation de processus métier.</p>
        <a href="{{ route('startup-lab') }}#ai" class="svc-link">En savoir plus <i class="fas fa-arrow-right fa-xs"></i></a>
      </div>
      <div class="service-card slide-up delay-1">
        <i class="fas fa-graduation-cap" style="font-size:2.2rem; color:#10B981;"></i>
        <h3>GOVIBE Academy</h3>
        <p>Formation professionnelle, cours en ligne et présentiel, certifications et incubation de startups.</p>
        <a href="{{ route('academy') }}" class="svc-link">S'inscrire <i class="fas fa-graduation-cap fa-xs"></i></a>
      </div>
      <div class="service-card slide-up delay-2">
        <i class="fas fa-wifi" style="font-size:2.2rem; color:#0891B2;"></i>
        <h3>GOVIBE Connect</h3>
        <p>Cartes NFC, smart menus numériques, étiquettes préventives et solutions IoT pour votre business.</p>
        <a href="#reservation" class="svc-link">Demander <i class="fas fa-arrow-right fa-xs"></i></a>
      </div>
    </div>

    <div class="slide-up" style="display:flex; flex-wrap:wrap; align-items:center; gap:2.5rem; background:#f8fafc; border-radius:20px; padding:2rem;">
      <div style="flex:1; min-width:260px;">
        <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
             alt="GOVIBE Innovation Hub" style="width:100%; border-radius:12px; object-fit:cover; max-height:280px;">
      </div>
      <div style="flex:1; min-width:260px;">
        <h3 style="font-family:'Anton',sans-serif; font-size:clamp(1.5rem,3vw,2rem); margin-bottom:1rem;">Une infrastructure complète pour votre réussite</h3>
        <p style="color:#64748b; line-height:1.75; margin-bottom:1.5rem;">Du coworking aux solutions digitales avancées, GOVIBE vous offre tout l'écosystème dont vous avez besoin pour croître et prospérer.</p>
        <a href="#reservation" class="gv-btn-prim">Découvrir tous nos services →</a>
      </div>
    </div>
  </div>
</section>

{{-- ===== PROGRAMMES ===== --}}
<section id="programmes" class="gv-section-gray">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-rocket"></i> NOS PROGRAMMES</span>
      <h2 class="gv-h2">GOVIBE <span style="background:linear-gradient(135deg,#DC2626,#ff6b6b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Programmes</span></h2>
      <p class="gv-sub" style="margin:0 auto; text-align:center;">Accompagnement et financement des entrepreneurs haïtiens.</p>
    </div>

    <div class="prog-grid" style="display:grid; grid-template-columns:repeat(2,1fr); gap:2rem;">
      <div class="programme-card slide-up">
        <div style="position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,#DC2626,#ff6b6b);"></div>
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
          <div style="width:50px;height:50px;background:rgba(220,38,38,.1);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#DC2626;">
            <i class="fas fa-rocket"></i>
          </div>
          <h3>Programme d'Incubation</h3>
        </div>
        <p style="color:#64748b; line-height:1.7; margin-bottom:1.2rem;">Accompagnement complet des startups, de l'idée à la levée de fonds avec mentorat d'experts.</p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-bottom:1.5rem;">
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Structuration idée</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Business model</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Prototype MVP</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Mentorat expert</span>
        </div>
        <a href="#reservation" class="gv-btn-prim">Postuler →</a>
      </div>

      <div class="programme-card slide-up delay-2">
        <div style="position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,#10B981,#DC2626);"></div>
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
          <div style="width:50px;height:50px;background:rgba(16,185,129,.1);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#10B981;">
            <i class="fas fa-credit-card"></i>
          </div>
          <h3>Crédit Digital</h3>
        </div>
        <p style="color:#64748b; line-height:1.7; margin-bottom:1.2rem;">Faciliter l'accès au financement pour entrepreneurs et PME haïtiennes via l'IA.</p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-bottom:1.5rem;">
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-bolt" style="color:#10B981;"></i> Microcrédit</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-chart-line" style="color:#10B981;"></i> Scoring IA</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-clock" style="color:#10B981;"></i> Paiement flexible</span>
          <span style="font-size:.87rem; color:#475569;"><i class="fas fa-chart-bar" style="color:#10B981;"></i> Suivi digital</span>
        </div>
        <a href="#reservation" class="gv-btn-prim green">Faire une demande →</a>
      </div>
    </div>

    <div class="slide-up" style="background:linear-gradient(135deg,#0a0000,#1a0004);border-radius:24px;padding:3rem;margin-top:3rem;color:#fff;">
      <span class="gv-stag" style="color:#fff;background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);"><i class="fas fa-bullseye"></i> NOTRE VISION</span>
      <h3 style="font-family:'Anton',sans-serif;font-size:clamp(1.8rem,4vw,2.5rem);margin:1rem 0 2rem;">Créer un écosystème intégré où :</h3>
      <div class="vision-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;">
        <div style="display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.04);border-radius:16px;padding:1.2rem;">
          <i class="fas fa-building" style="font-size:2rem;color:#DC2626;flex-shrink:0;"></i>
          <p>Les entreprises trouvent l'infrastructure dont elles ont besoin</p>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.04);border-radius:16px;padding:1.2rem;">
          <i class="fas fa-rocket" style="font-size:2rem;color:#10B981;flex-shrink:0;"></i>
          <p>Les startups trouvent l'accompagnement pour croître</p>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.04);border-radius:16px;padding:1.2rem;">
          <i class="fas fa-lightbulb" style="font-size:2rem;color:#ffe800;flex-shrink:0;"></i>
          <p>Les institutions trouvent des solutions digitales sur mesure</p>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.04);border-radius:16px;padding:1.2rem;">
          <i class="fas fa-coins" style="font-size:2rem;color:#a855f7;flex-shrink:0;"></i>
          <p>Les entrepreneurs trouvent le financement pour réaliser leurs projets</p>
        </div>
      </div>
      <div style="text-align:center;margin-top:2.5rem;">
        <a href="#reservation" class="gv-btn-prim">Rejoindre l'écosystème →</a>
      </div>
    </div>
  </div>
</section>

{{-- ===== STARTUPS & DIGITAL ===== --}}
<section id="startups-digital" class="gv-section-light">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-code"></i> STARTUPS &amp; DIGITAL</span>
      <h2 class="gv-h2">Construisez. Lancez.<br>
        <span style="background:linear-gradient(135deg,#DC2626,#10B981,#ff6b6b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Scalez.</span>
      </h2>
      <p class="gv-sub" style="margin:0 auto; text-align:center;">Développement d'applications, IA, ERP/CRM, plateformes SaaS et cybersécurité.</p>
    </div>

    <div class="dig-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:1.8rem;">
      <div class="service-digital slide-up">
        <i class="fas fa-globe" style="font-size:2rem; color:#2563EB;"></i>
        <h3>Web Development</h3>
        <p style="color:#64748b; font-size:.88rem;">Sites, apps web, e-commerce modernes et responsives.</p>
        <a href="{{ route('startup-lab') }}#web" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
      <div class="service-digital slide-up delay-1">
        <i class="fas fa-mobile-alt" style="font-size:2rem; color:#10B981;"></i>
        <h3>Applications Mobiles</h3>
        <p style="color:#64748b; font-size:.88rem;">iOS &amp; Android, MVP rapide, déploiement App Store.</p>
        <a href="{{ route('startup-lab') }}#mobile" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
      <div class="service-digital slide-up delay-2">
        <i class="fas fa-robot" style="font-size:2rem; color:#DC2626;"></i>
        <h3>IA &amp; Automatisation</h3>
        <p style="color:#64748b; font-size:.88rem;">Chatbots, agents IA, analyse de données, RPA.</p>
        <a href="{{ route('startup-lab') }}#ai" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
      <div class="service-digital slide-up">
        <i class="fas fa-laptop-code" style="font-size:2rem; color:#2563EB;"></i>
        <h3>ERP &amp; CRM</h3>
        <p style="color:#64748b; font-size:.88rem;">Logiciels sur mesure, gestion d'entreprise intégrée.</p>
        <a href="{{ route('startup-lab') }}#erp" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
      <div class="service-digital slide-up delay-1">
        <i class="fas fa-cloud-upload-alt" style="font-size:2rem; color:#10B981;"></i>
        <h3>Plateformes SaaS</h3>
        <p style="color:#64748b; font-size:.88rem;">Logiciels en ligne, multi-tenant, déploiement cloud.</p>
        <a href="{{ route('startup-lab') }}#saas" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
      <div class="service-digital slide-up delay-2">
        <i class="fas fa-shield-alt" style="font-size:2rem; color:#DC2626;"></i>
        <h3>Cybersécurité</h3>
        <p style="color:#64748b; font-size:.88rem;">Audit sécurité, protection données, conformité RGPD.</p>
        <a href="{{ route('startup-lab') }}#cyber" class="svc-link" style="margin-top:.8rem;">Voir détails →</a>
      </div>
    </div>

    <div class="slide-up" style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:24px;padding:2.5rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-top:3rem;color:#fff;gap:1.5rem;">
      <div>
        <h3 style="font-family:'Anton',sans-serif;font-size:1.8rem;margin-bottom:.5rem;">Prêt à lancer votre projet digital ?</h3>
        <p style="color:rgba(255,255,255,.6);font-size:.92rem;">De l'idée au déploiement, notre équipe de 50+ experts vous accompagne.</p>
      </div>
      <a href="{{ route('startup-lab') }}" class="gv-btn-prim"><i class="fas fa-rocket"></i> Démarrer un projet →</a>
    </div>
  </div>
</section>

{{-- ===== CONFIANCE ===== --}}
<section id="confiance" class="gv-section-gray">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-star"></i> CONFIANCE &amp; SÉRÉNITÉ</span>
      <h2 class="gv-h2">Pourquoi <span style="color:#DC2626;">GOVIBE</span> est le partenaire de confiance ?</h2>
    </div>

    <div class="conf-stat-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:1.5rem;">
      <div class="confiance-card slide-up">
        <i class="fas fa-trophy" style="font-size:2rem;color:#DC2626;"></i>
        <h3>Expertise reconnue</h3>
        <p style="color:#64748b;font-size:.88rem;">Fondé en 2020 avec une vision internationale dès le départ.</p>
      </div>
      <div class="confiance-card slide-up delay-1">
        <i class="fas fa-lock" style="font-size:2rem;color:#10B981;"></i>
        <h3>Sécurité des données</h3>
        <p style="color:#64748b;font-size:.88rem;">Infrastructure sécurisée, protection et conformité RGPD.</p>
      </div>
      <div class="confiance-card slide-up delay-2">
        <i class="fas fa-handshake" style="font-size:2rem;color:#2563EB;"></i>
        <h3>Accompagnement sur mesure</h3>
        <p style="color:#64748b;font-size:.88rem;">Équipe dédiée de 36 experts passionnés pour vous servir.</p>
      </div>
      <div class="confiance-card slide-up delay-3">
        <i class="fas fa-chart-line" style="font-size:2rem;color:#ffe800;"></i>
        <h3>Résultats prouvés</h3>
        <p style="color:#64748b;font-size:.88rem;">+1000 clients satisfaits en Haïti, USA, Canada et Afrique.</p>
      </div>
    </div>

    <div class="conf-testi-grid" style="display:grid; grid-template-columns:repeat(2,1fr); gap:2rem; margin-top:3rem;">
      <div class="temoignage-card slide-up">
        <div style="display:flex; gap:1rem; margin-bottom:1rem;">
          <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-1.2.1&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
               style="width:56px;height:56px;border-radius:50%;object-fit:cover;" alt="Témoignage">
          <div>
            <h4 style="font-family:'Anton',sans-serif;font-size:1rem;color:#0f172a;">Jean-Marc H.</h4>
            <p style="color:#DC2626;font-size:.82rem;font-weight:600;">PDG — Delimart Haiti</p>
          </div>
        </div>
        <p style="color:#475569;line-height:1.7;font-size:.92rem;">"GOVIBE a complètement digitalisé nos processus. Un vrai partenaire technologique de confiance qui comprend les réalités haïtiennes."</p>
        <div style="margin-top:.8rem;">
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
        </div>
      </div>
      <div class="temoignage-card slide-up delay-2">
        <div style="display:flex; gap:1rem; margin-bottom:1rem;">
          <img src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?ixlib=rb-1.2.1&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
               style="width:56px;height:56px;border-radius:50%;object-fit:cover;" alt="Témoignage">
          <div>
            <h4 style="font-family:'Anton',sans-serif;font-size:1rem;color:#0f172a;">Sophie D.</h4>
            <p style="color:#10B981;font-size:.82rem;font-weight:600;">Fondatrice Startup Tech</p>
          </div>
        </div>
        <p style="color:#475569;line-height:1.7;font-size:.92rem;">"Grâce au programme d'incubation de GOVIBE, nous avons lancé notre MVP en seulement 3 mois. L'accompagnement est exceptionnel."</p>
        <div style="margin-top:.8rem;">
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
          <i class="fas fa-star" style="color:#fbbf24;font-size:.85rem;"></i>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ===== POURQUOI GOVIBE ===== --}}
<section id="pourquoi" class="gv-section-dark">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center; margin-bottom:2.8rem;">
      <span class="gv-stag" style="color:rgba(255,255,255,.5);background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.12);">
        <i class="fas fa-question-circle"></i> Pourquoi GOVIBE ?
      </span>
      <h2 style="font-family:'Anton',sans-serif;font-size:clamp(1.85rem,4.5vw,2.85rem);line-height:1.1;color:#fff;">
        Pourquoi Choisir<br><span style="color:#DC2626;">GOVIBE ?</span>
      </h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.8rem;">
      <a href="{{ route('about') }}" class="pourquoi-card slide-up">
        <div style="font-size:1.9rem;margin-bottom:.55rem;color:#DC2626;"><i class="fas fa-cubes"></i></div>
        <h3>Infrastructure-First</h3>
        <p style="color:#94a3b8;">Nous construisons des fondations solides pour une scalabilité à long terme.</p>
      </a>
      <a href="{{ route('about') }}" class="pourquoi-card slide-up delay-1">
        <div style="font-size:1.9rem;margin-bottom:.55rem;color:#10B981;"><i class="fas fa-sync-alt"></i></div>
        <h3>Modèle Hybride</h3>
        <p style="color:#94a3b8;">Physique + Numérique + Financier en un seul écosystème intégré.</p>
      </a>
      <a href="{{ route('about') }}" class="pourquoi-card slide-up delay-2">
        <div style="font-size:1.9rem;margin-bottom:.55rem;color:#ffe800;"><i class="fas fa-globe-americas"></i></div>
        <h3>Base Haïtienne, Vision Mondiale</h3>
        <p style="color:#94a3b8;">Enregistré aux USA et en Haïti. Standards internationaux.</p>
      </a>
      <a href="{{ route('about') }}#team" class="pourquoi-card slide-up delay-3">
        <div style="font-size:1.9rem;margin-bottom:.55rem;color:#2563EB;"><i class="fas fa-users"></i></div>
        <h3>Communauté Active</h3>
        <p style="color:#94a3b8;">36 experts dévoués + réseau d'entrepreneurs et innovateurs.</p>
      </a>
    </div>
  </div>
</section>

{{-- ===== ACADEMY CTA ===== --}}
<section style="padding:70px 2rem; background:linear-gradient(135deg,#f8fafc,#fff);">
  <div class="gv-wrap">
    <div class="slide-up" style="background:linear-gradient(135deg,#DC2626,#991b1b);border-radius:24px;padding:3rem;text-align:center;color:#fff;">
      <span style="display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.15);border-radius:50px;padding:.3rem 1rem;font-size:.75rem;font-weight:700;letter-spacing:.15em;margin-bottom:1.2rem;">
        <i class="fas fa-graduation-cap"></i> GOVIBE ACADEMY
      </span>
      <h2 style="font-family:'Anton',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);margin-bottom:1rem;">
        Rejoignez la prochaine génération<br>d'innovateurs haïtiens
      </h2>
      <p style="color:rgba(255,255,255,.8);max-width:560px;margin:0 auto 2rem;line-height:1.75;">
        Marketing Digital, Développement Web, Entrepreneuriat, Design Graphique et bien plus.
        Formations en présentiel et en ligne à travers Haïti.
      </p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('inscription.create') }}" style="display:inline-flex;align-items:center;gap:.4rem;background:#fff;color:#DC2626;padding:.85rem 1.8rem;border-radius:50px;font-weight:700;font-size:.95rem;transition:all .3s;">
          <i class="fas fa-user-plus"></i> S'inscrire maintenant
        </a>
        <a href="{{ route('academy') }}" class="gv-btn-secondary" style="border-color:rgba(255,255,255,.4);">
          <i class="fas fa-book"></i> Voir les formations
        </a>
      </div>
    </div>
  </div>
</section>

{{-- ===== RÉSERVATION ===== --}}
<section id="reservation" style="background:linear-gradient(135deg,#0a0000,#1a0004);padding:80px 2rem;">
  <div class="gv-wrap">
    <div class="reservation-grid" style="display:grid;grid-template-columns:1fr 1.35fr;gap:3.5rem;align-items:start;">
      <div class="slide-up">
        <span class="gv-stag" style="color:#10b981;background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.28);">
          <i class="fas fa-calendar-check"></i> Réservations
        </span>
        <h2 style="font-family:'Anton',sans-serif;font-size:clamp(1.85rem,4vw,2.75rem);color:#fff;line-height:1.14;margin:.75rem 0 .9rem;">
          Réservez Votre<br><span style="color:#DC2626;">Espace Aujourd'hui</span>
        </h2>
        <p style="color:rgba(255,255,255,.62);line-height:1.75;margin-bottom:1.8rem;">
          Prêt à rejoindre l'écosystème GOVIBE ? Remplissez le formulaire pour réserver votre
          espace de travail, salle de conférence ou tout autre service. Confirmation sous 24h.
        </p>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
          <div style="display:flex;align-items:center;gap:.75rem;color:rgba(255,255,255,.72);font-size:.9rem;">
            <div style="width:34px;height:34px;border-radius:8px;background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.3);display:flex;align-items:center;justify-content:center;color:#DC2626;">
              <i class="fas fa-bolt"></i>
            </div>Confirmation sous 24h
          </div>
          <div style="display:flex;align-items:center;gap:.75rem;color:rgba(255,255,255,.72);font-size:.9rem;">
            <div style="width:34px;height:34px;border-radius:8px;background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.3);display:flex;align-items:center;justify-content:center;color:#DC2626;">
              <i class="fas fa-undo-alt"></i>
            </div>Annulation gratuite jusqu'à 48h avant
          </div>
          <div style="display:flex;align-items:center;gap:.75rem;color:rgba(255,255,255,.72);font-size:.9rem;">
            <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,.2);border:1px solid rgba(16,185,129,.3);display:flex;align-items:center;justify-content:center;color:#10b981;">
              <i class="fab fa-whatsapp"></i>
            </div>
            <a href="https://wa.me/50933988754" style="color:rgba(255,255,255,.72);">Support WhatsApp +509 3398-8754</a>
          </div>
        </div>
        <div style="margin-top:2rem;padding:1.2rem;background:rgba(255,255,255,.04);border-radius:12px;border:1px solid rgba(255,255,255,.08);">
          <p style="color:rgba(255,255,255,.5);font-size:.82rem;margin-bottom:.7rem;">Pour une inscription formation :</p>
          <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="font-size:.85rem;padding:.6rem 1.2rem;">
            <i class="fas fa-graduation-cap"></i> GOVIBE Academy →
          </a>
        </div>
      </div>

      <div class="slide-up delay-2" style="background:rgba(255,255,255,.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:2.3rem;">
        <h3 style="font-family:'Anton',sans-serif;font-size:1.4rem;color:#fff;margin-bottom:1.4rem;">
          <i class="fas fa-clipboard-list"></i> Formulaire de Réservation
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.95rem;margin-bottom:.95rem;">
          <div><label>Prénom *</label><input id="gv_fname" type="text" placeholder="Jean"></div>
          <div><label>Nom *</label><input id="gv_lname" type="text" placeholder="Pierre"></div>
          <div><label>Email *</label><input id="gv_email" type="email" placeholder="jean@email.com"></div>
          <div><label>Téléphone</label><input id="gv_phone" type="tel" placeholder="+509 XXXX XXXX"></div>
        </div>
        <div style="margin-bottom:.95rem;">
          <label>Service Demandé *</label>
          <select id="gv_service">
            <option value="" disabled selected>Sélectionnez...</option>
            <option value="hotdesk">Coworking — Bureau Journalier</option>
            <option value="monthly">Coworking — Bureau Mensuel</option>
            <option value="meeting">Salle de Réunion</option>
            <option value="callcenter">Services Call Center</option>
            <option value="startup">Programme Incubation</option>
            <option value="web">Développement Web / Mobile</option>
            <option value="erp">ERP / CRM / Logiciel sur mesure</option>
            <option value="ai">IA &amp; Automatisation</option>
            <option value="credit">Crédit Digital</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.95rem;margin-bottom:.95rem;">
          <div>
            <label>Date Préférée *</label>
            <input id="gv_date" type="date" style="color-scheme:dark;">
          </div>
          <div>
            <label>Personnes</label>
            <select id="gv_people">
              <option>1 personne</option>
              <option>2–5 personnes</option>
              <option>6–10 personnes</option>
              <option>10+ personnes</option>
            </select>
          </div>
        </div>
        <div style="margin-bottom:1.2rem;">
          <label>Message</label>
          <textarea id="gv_msg" placeholder="Décrivez votre besoin..." style="resize:vertical;min-height:90px;"></textarea>
        </div>
        <button id="gvResBtn" onclick="gvSubmitRes()" class="gv-btn-prim" style="width:100%;padding:.95rem;border-radius:50px;justify-content:center;font-size:.95rem;border:none;">
          <i class="fas fa-paper-plane"></i> Envoyer la Demande de Réservation
        </button>
        <p style="text-align:center;font-size:.75rem;color:rgba(255,255,255,.35);margin-top:.75rem;">
          <i class="fas fa-lock"></i> Vos informations sont sécurisées et confidentielles.
        </p>
        <div id="gvResSuccess" style="display:none;text-align:center;padding:1.2rem;background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.28);border-radius:12px;color:#10b981;font-weight:600;margin-top:.9rem;">
          <i class="fas fa-check-circle"></i> Merci ! Votre demande a été reçue. Nous vous contacterons sous 24h.
        </div>
      </div>
    </div>
  </div>
</section>

@endsection

@section('scripts')
<script>
/* ── TYPING ANIMATION ─────────────────────────────── */
(function(){
  const WORDS = {
    fr:[{text:'Entrepreneurs Visionnaires.',color:'#DC2626'},{text:'Startups Innovantes',color:'#10B981'},{text:'Plateformes Digitales.',color:'#a855f7'},{text:'Solutions avec IA.',color:'#f59e0b'},{text:'Centres d\'Appels.',color:'#0891B2'},{text:'Coworking Space',color:'#2563EB'},{text:'L\'Avenir Numérique.',color:'#10B981'}],
    en:[{text:'Visionary Entrepreneurs.',color:'#DC2626'},{text:'Innovative Startups',color:'#10B981'},{text:'Digital Platforms.',color:'#a855f7'},{text:'AI Solutions.',color:'#f59e0b'},{text:'Call Centers.',color:'#0891B2'},{text:'Coworking Space',color:'#2563EB'},{text:'The Digital Future.',color:'#10B981'}],
    cr:[{text:'Antreprenè Vyzyone.',color:'#DC2626'},{text:'Startup Inovatè',color:'#10B981'},{text:'Platfòm Dijital.',color:'#a855f7'},{text:'Solisyon IA.',color:'#f59e0b'},{text:'Sant Apèl.',color:'#0891B2'},{text:'Kowoking Espas',color:'#2563EB'},{text:'Avni Nimerik.',color:'#10B981'}],
    es:[{text:'Emprendedores Visionarios.',color:'#DC2626'},{text:'Startups Innovadoras',color:'#10B981'},{text:'Plataformas Digitales.',color:'#a855f7'},{text:'Soluciones con IA.',color:'#f59e0b'},{text:'Call Centers.',color:'#0891B2'},{text:'Coworking Space',color:'#2563EB'},{text:'El Futuro Digital.',color:'#10B981'}]
  };
  let wi=0,ci=0,del=false,lang=localStorage.getItem('gv_lang')||'fr';
  const tw=document.getElementById('gvTypingWord');
  if(!tw) return;
  function typeIt(){
    const words=WORDS[lang]||WORDS.fr, w=words[wi%words.length];
    if(!del){
      tw.textContent=w.text.substring(0,ci+1);
      tw.style.color=w.color;
      tw.style.textShadow=`0 0 25px ${w.color}80`;
      ci++;
      if(ci===w.text.length){del=true;return setTimeout(typeIt,1600);}
    } else {
      tw.textContent=w.text.substring(0,ci-1);
      ci--;
      if(ci===0){del=false;wi=(wi+1)%words.length;return setTimeout(typeIt,400);}
    }
    setTimeout(typeIt,del?55:100);
  }
  typeIt();
  window.GV_PAGE_TRANSLATIONS=function(l){lang=l;};
})();

/* ── ANTIGRAVITY PARTICLES ────────────────────────── */
(function(){
  const C=document.getElementById('gv-ag-canvas');
  if(!C) return;
  const mob=window.innerWidth<768;
  const P=[{h:'#DC2626',r:'220,38,38'},{h:'#991b1b',r:'153,27,27'},{h:'#10B981',r:'16,185,129'},{h:'#ff6b6b',r:'255,107,107'},{h:'#2563EB',r:'37,99,235'}];
  function r(a,b){return Math.random()*(b-a)+a;}
  function pk(a){return a[Math.floor(Math.random()*a.length)];}
  function pct(){return r(2,96).toFixed(1)+'%';}
  function afv(ax,ay,spin,op){
    return `--x25:${r(-ax,ax).toFixed(1)}px;--y25:${r(-ay*1.1,-ay*.25).toFixed(1)}px;--x50:${r(-ax*1.6,ax*1.6).toFixed(1)}px;--y50:${r(-ay*2.2,-ay*.5).toFixed(1)}px;--x75:${r(-ax,ax*1.3).toFixed(1)}px;--y75:${r(-ay*1.5,-ay*.15).toFixed(1)}px;--r0:${r(-spin,spin).toFixed(1)}deg;--r25:${r(-spin*2,spin*2).toFixed(1)}deg;--r50:${r(-spin*1.3,spin*1.3).toFixed(1)}deg;--r75:${r(-spin*1.7,spin*1.7).toFixed(1)}deg;--s25:1.04;--s50:.97;--s75:1.02;--o0:${op.toFixed(3)};--o1:${(op*1.55).toFixed(3)};`;
  }
  const words=['GOVIBE','STARTUP','DIGITAL','AI','INNOVATION','HAÏTI','TECH','CLOUD','ERP','SaaS'];
  for(let i=0;i<(mob?5:10);i++){
    const e=document.createElement('div');e.className='gv-fw';
    const c=pk(P),op=r(.035,.08),sz=r(mob?10:14,mob?16:22),du=r(20,35),dl=r(0,-du);
    e.style.cssText=`left:${pct()};top:${pct()};font-size:${sz}px;color:${c.h};opacity:${op};${afv(42,52,13,op)}animation:gvAF ${du}s ease-in-out ${dl}s infinite;`;
    e.textContent=words[i%words.length];C.appendChild(e);
  }
  for(let d=0;d<25;d++){
    const e=document.createElement('div');
    const c=pk(P),sz=r(2,6),op=r(.1,.28),du=r(14,30),dl=r(0,-du);
    e.style.cssText=`position:absolute;border-radius:50%;left:${pct()};top:${pct()};width:${sz}px;height:${sz}px;background:rgba(${c.r},${op});${afv(25,48,0,op)}animation:gvAF ${du}s ease-in-out ${dl}s infinite;`;
    C.appendChild(e);
  }
})();

/* ── 3D LOGO EFFECT ──────────────────────────────── */
const logo3d=document.getElementById('logo3d');
if(logo3d&&window.innerWidth>600){
  document.addEventListener('mousemove',function(e){
    const cx=window.innerWidth/2,cy=window.innerHeight/2;
    const rx=((e.clientY-cy)/cy)*-8,ry=((e.clientX-cx)/cx)*8;
    logo3d.style.transform=`rotateX(${rx}deg) rotateY(${ry}deg)`;
  });
  document.addEventListener('mouseleave',function(){logo3d.style.transform='rotateX(0deg) rotateY(0deg)';});
}

/* ── RESERVATION FORM ────────────────────────────── */
function gvSubmitRes(){
  const fn=document.getElementById('gv_fname').value.trim();
  const em=document.getElementById('gv_email').value.trim();
  const sv=document.getElementById('gv_service').value;
  const dt=document.getElementById('gv_date').value;
  if(!fn||!em||!sv||!dt){alert('Veuillez remplir tous les champs obligatoires (*).'); return;}
  const btn=document.getElementById('gvResBtn');
  btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';btn.disabled=true;
  setTimeout(function(){
    btn.innerHTML='<i class="fas fa-paper-plane"></i> Envoyer la Demande de Réservation';btn.disabled=false;
    document.getElementById('gvResSuccess').style.display='block';
    ['gv_fname','gv_lname','gv_email','gv_phone','gv_msg'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    document.getElementById('gv_service').selectedIndex=0;document.getElementById('gv_date').value='';
    setTimeout(()=>{document.getElementById('gvResSuccess').style.display='none';},6000);
  },1200);
}
(function(){const d=document.getElementById('gv_date');if(d)d.setAttribute('min',new Date().toISOString().split('T')[0]);})();
</script>
@endsection
