/* =====================================================
   TCHEKELA — Shared JS
   Toast, topbar injection, AI assistant, helpers, storage.
   ===================================================== */

/* ---------- Toast ---------- */
function toast(msg, ms) {
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), ms || 3500);
}

/* ---------- Storage helpers (shared "DB" via localStorage) ---------- */
const TKStore = {
  get(key, fallback) {
    try { return JSON.parse(localStorage.getItem('tch_' + key) || 'null') ?? fallback; }
    catch (e) { return fallback; }
  },
  set(key, val) { localStorage.setItem('tch_' + key, JSON.stringify(val)); },
  push(key, item) { const arr = TKStore.get(key, []); arr.unshift(item); TKStore.set(key, arr); return arr; }
};

/* ---------- Misc helpers ---------- */
function ref(prefix) {
  const y = new Date().getFullYear();
  return prefix + '-' + y + '-' + String(Math.floor(Math.random() * 90000) + 10000);
}
function fileToDataURL(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload = e => res(e.target.result);
    r.onerror = rej;
    r.readAsDataURL(file);
  });
}
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}
function escapeHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* ---------- Topbar (shared, internal pages) ---------- */
function injectTopbar(active) {
  const links = [
    { href: 'index.html', label: 'Accueil', id: 'home' },
    { href: 'verifier-carte.html', label: 'Vérifier carte', id: 'verifier' },
    { href: 'documents.html', label: 'Documents', id: 'documents' },
    { href: 'partenaires.html', label: 'Partenaires', id: 'partenaires' },
    { href: 'benevolat.html', label: 'Bénévolat', id: 'benevolat' },
  ];
  const navHtml = links.map(l => `<a href="${l.href}" class="${active === l.id ? 'active' : ''}">${l.label}</a>`).join('');
  const bar = document.createElement('div');
  bar.id = 'topbar';
  bar.innerHTML = `
    <a href="index.html" class="tb-logo">
      <div class="tb-logo-mark"><i class="fas fa-shield-halved"></i></div>
      <div class="tb-logo-text"><span>TCHEKELA</span><small>Retrouvons ensemble</small></div>
    </a>
    <nav class="tb-nav">${navHtml}</nav>
    <a href="don.html" class="tb-cta"><i class="fas fa-heart"></i> Faire un don</a>`;
  document.body.prepend(bar);
}

/* ---------- Footer (shared) ---------- */
function injectFooter() {
  const f = document.createElement('footer');
  f.innerHTML = `
    <div class="wrap">
      <div class="ft-grid">
        <div class="ft-brand">
          <div class="ft-brand-logo"><div class="ft-brand-logo-mark"><i class="fas fa-shield-halved"></i></div>TCHEKELA</div>
          <p>Plateforme collaborative haïtienne gratuite pour déclarer, retrouver, vérifier et gérer vos documents.</p>
          <div class="ft-socials">
            <a href="https://wa.me/50933988754" class="ft-soc"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <a href="#" class="ft-soc"><i class="fab fa-facebook-f"></i> Facebook</a>
            <a href="#" class="ft-soc"><i class="fab fa-instagram"></i> Instagram</a>
          </div>
        </div>
        <div class="ft-col"><h5>Services</h5><ul>
          <li><a href="index.html"><i class="fas fa-house"></i>Accueil</a></li>
          <li><a href="verifier-carte.html"><i class="fas fa-id-card-clip"></i>Vérifier ma carte</a></li>
          <li><a href="documents.html"><i class="fas fa-folder-open"></i>Mes documents</a></li>
          <li><a href="don.html"><i class="fas fa-heart"></i>Faire un don</a></li>
        </ul></div>
        <div class="ft-col"><h5>Rejoindre</h5><ul>
          <li><a href="partenaires.html"><i class="fas fa-handshake"></i>Devenir partenaire</a></li>
          <li><a href="benevolat.html"><i class="fas fa-hand-holding-heart"></i>Devenir bénévole</a></li>
          <li><a href="admin.html"><i class="fas fa-user-shield"></i>Espace admin</a></li>
        </ul></div>
        <div class="ft-col"><h5>Contact</h5><ul>
          <li><a href="mailto:info@tchekela.com"><i class="fas fa-envelope"></i>info@tchekela.com</a></li>
          <li><a href="tel:+50933988754"><i class="fas fa-phone"></i>+509 3398 8754</a></li>
        </ul></div>
      </div>
      <div class="ft-bot">
        <span>© ${new Date().getFullYear()} TCHEKELA — Tous droits réservés</span>
        <span><i class="fas fa-heart" style="color:var(--red);"></i> powered by GOVIBE · govibeht.com</span>
      </div>
    </div>`;
  document.body.appendChild(f);
}

/* =====================================================
   AI ASSISTANT — "Tchecko"
   Rule-based knowledge base + optional API integration point.
   To connect a real model, implement window.TCHEKELA_AI_BACKEND(message, history)
   returning a Promise<string>; otherwise local answers are used.
   ===================================================== */
const AI_KB = [
  { k: ['don', 'donner', 'donation', 'soutenir', 'contribuer', 'payer'], a: "Pour faire un don, rendez-vous sur la <a href='don.html'>page de don</a>. Nous acceptons MonCash, NatCash, PayPal, Zelle, crypto (USDT, BTC, ETH) et virements Unibank/Sogebank. Après paiement, vous téléversez la preuve et recevez un reçu téléchargeable." },
  { k: ['carte', 'oni', 'cin', 'identité', 'prête', 'imprimé', 'bureau', 'retrait'], a: "Pour savoir si votre carte est prête à l'ONI, utilisez la page <a href='verifier-carte.html'>Vérifier ma carte</a>. Saisissez votre numéro de dossier — nos bénévoles téléversent les cartes arrivées au bureau et vous voyez le statut en temps réel." },
  { k: ['partenaire', 'entreprise', 'institution', 'point de collecte', 'partenariat'], a: "Votre entreprise peut nous rejoindre via la page <a href='partenaires.html'>Partenaires</a> : point de collecte physique, partenariat financier, média, etc. Après approbation par l'admin, votre logo s'affiche sur le site." },
  { k: ['benevole', 'bénévole', 'benevolat', 'bénévolat', 'rejoindre', 'badge', 'volontaire', 'famille'], a: "Bienvenue dans la famille TCHEKELA ! Inscrivez-vous sur la page <a href='benevolat.html'>Bénévolat</a>. Le formulaire est rapide et génère automatiquement un badge avec votre photo, à partager sur les réseaux." },
  { k: ['document', 'portefeuille', 'wallet', 'numérique', 'stocker', 'sauvegarder'], a: "La page <a href='documents.html'>Mes documents</a> vous permet de stocker vos cartes de façon chiffrée sur votre appareil, générer un QR de partage et exporter une sauvegarde." },
  { k: ['déclarer', 'declarer', 'trouvé', 'trouve', 'perdu', 'rechercher', 'chercher'], a: "Vous avez trouvé un document ? Déclarez-le depuis l'accueil (bouton <b>Déclarer</b>). Vous cherchez le vôtre ? Utilisez la section <a href='index.html#rechercher'>Rechercher</a>." },
  { k: ['contact', 'téléphone', 'telephone', 'whatsapp', 'email', 'joindre'], a: "Contactez-nous sur WhatsApp <a href='https://wa.me/50933988754'>+509 3398 8754</a> ou par email <a href='mailto:info@tchekela.com'>info@tchekela.com</a>." },
  { k: ['gratuit', 'prix', 'coût', 'cout', 'payant'], a: "TCHEKELA est 100% gratuit. La plateforme vit grâce aux dons et aux partenaires. 🇭🇹" },
];
const AI_DEFAULT = "Je suis Tchecko, l'assistant TCHEKELA 🤖. Je peux vous aider sur : faire un don, vérifier une carte à l'ONI, devenir partenaire ou bénévole, gérer vos documents. Que souhaitez-vous faire ?";

function aiAnswer(msg) {
  const m = (msg || '').toLowerCase();
  let best = null, score = 0;
  for (const entry of AI_KB) {
    const s = entry.k.reduce((acc, kw) => acc + (m.includes(kw) ? 1 : 0), 0);
    if (s > score) { score = s; best = entry; }
  }
  if (best && score > 0) return best.a;
  if (/bonjou|bonsoir|salut|hello|hi|allo|alo/.test(m)) return "Bonjou ! 👋 " + AI_DEFAULT;
  if (/merci|mèsi|thanks/.test(m)) return "Avèk plezi ! 🙏 N'hésitez pas si vous avez d'autres questions.";
  return AI_DEFAULT;
}

function injectAI() {
  const fab = document.createElement('button');
  fab.id = 'ai-fab';
  fab.setAttribute('aria-label', 'Assistant IA');
  fab.innerHTML = `<i class="fas fa-robot"></i><span class="ai-spark"><i class="fas fa-bolt"></i></span>`;
  const panel = document.createElement('div');
  panel.id = 'ai-panel';
  panel.innerHTML = `
    <div class="ai-head">
      <div class="ai-av"><i class="fas fa-robot"></i></div>
      <div class="ai-meta"><h4>Tchecko · Assistant</h4><span>En ligne · répond en 24/7</span></div>
      <button class="ai-x" aria-label="Fermer"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="ai-msgs" id="aiMsgs"></div>
    <div class="ai-chips" id="aiChips"></div>
    <form class="ai-input" id="aiForm">
      <input id="aiInput" type="text" placeholder="Posez votre question…" autocomplete="off">
      <button type="submit" aria-label="Envoyer"><i class="fas fa-paper-plane"></i></button>
    </form>`;
  document.body.appendChild(fab);
  document.body.appendChild(panel);

  const msgs = panel.querySelector('#aiMsgs');
  const chips = panel.querySelector('#aiChips');
  const form = panel.querySelector('#aiForm');
  const input = panel.querySelector('#aiInput');

  function addMsg(text, who) {
    const d = document.createElement('div');
    d.className = 'ai-msg ' + who;
    d.innerHTML = text;
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }
  function botRespond(text) {
    const typing = document.createElement('div');
    typing.className = 'ai-typing';
    typing.textContent = 'Tchecko écrit…';
    msgs.appendChild(typing);
    msgs.scrollTop = msgs.scrollHeight;
    const finish = (answer) => { typing.remove(); addMsg(answer, 'bot'); };
    if (typeof window.TCHEKELA_AI_BACKEND === 'function') {
      window.TCHEKELA_AI_BACKEND(text).then(finish).catch(() => finish(aiAnswer(text)));
    } else {
      setTimeout(() => finish(aiAnswer(text)), 550);
    }
  }
  function send(text) {
    if (!text.trim()) return;
    addMsg(escapeHtml(text), 'user');
    botRespond(text);
  }

  ['Faire un don', 'Ma carte est-elle prête ?', 'Devenir bénévole', 'Devenir partenaire'].forEach(label => {
    const c = document.createElement('button');
    c.type = 'button'; c.className = 'ai-chip'; c.textContent = label;
    c.onclick = () => send(label);
    chips.appendChild(c);
  });

  let opened = false;
  function open() {
    panel.classList.add('open');
    if (!opened) { addMsg(AI_DEFAULT, 'bot'); opened = true; }
    setTimeout(() => input.focus(), 100);
  }
  function close() { panel.classList.remove('open'); }
  fab.onclick = () => panel.classList.contains('open') ? close() : open();
  panel.querySelector('.ai-x').onclick = close;
  form.onsubmit = e => { e.preventDefault(); send(input.value); input.value = ''; };
}

/* ---------- Auto-init shared chrome ---------- */
document.addEventListener('DOMContentLoaded', () => {
  if (document.body.hasAttribute('data-shared-chrome')) {
    injectTopbar(document.body.getAttribute('data-page') || '');
    // footer injected by page if it has data-shared-footer
    if (document.body.hasAttribute('data-shared-footer')) injectFooter();
  }
  if (!document.body.hasAttribute('data-no-ai')) injectAI();
  document.querySelectorAll('.reveal').forEach(el => {
    new IntersectionObserver((e) => { if (e[0].isIntersecting) el.classList.add('on'); }, { threshold: .1 }).observe(el);
  });
});
