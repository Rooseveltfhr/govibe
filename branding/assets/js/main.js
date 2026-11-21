/* ============================================================
   ROOSEVELT FORESTAL — interactions communes (aucune dépendance)
   ============================================================ */
(function () {
  "use strict";

  /* ---- CONFIG : modifier ici uniquement ---- */
  var RF = (window.RF = window.RF || {});
  Object.assign(RF, {
    calendly: "https://calendly.com/rooseveltforestal",
    whatsapp: "https://wa.me/message/5FJZXWSPZRJHB1",
    facebook: "https://www.facebook.com/share/1ELxgXJDTx/",
    linkedin: "https://www.linkedin.com/in/roosevelt-forestal-530864145",
    email: "mr@rooseveltforestal.com",
    site: "https://rooseveltforestal.com",
    campaignPath: "nou-pa-nan-bel-diskou-anko",
  });

  /* ---- Racine du site (fonctionne aussi dans un sous-dossier) ---- */
  var me = document.currentScript || document.querySelector('script[src*="assets/js/main.js"]');
  RF.base = me ? new URL("../../", me.src).pathname : "/"; // ex. "/" ou "/sous-dossier/"

  /* ---- Utilitaires ---- */
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  RF.toast = function (msg, ms) {
    var t = $("#rf-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "rf-toast";
      t.setAttribute("role", "status");
      t.setAttribute("aria-live", "polite");
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(RF._tt);
    RF._tt = setTimeout(function () { t.classList.remove("show"); }, ms || 2600);
  };

  /* ---- Statistiques respectueuses : événements anonymes, aucune donnée perso ---- */
  RF.track = function (event, ref) {
    try {
      var body = JSON.stringify({ e: event, r: ref ? String(ref).slice(0, 80) : "" });
      var url = RF.base + "api/track";
      if (navigator.sendBeacon) navigator.sendBeacon(url, new Blob([body], { type: "application/json" }));
      else fetch(url, { method: "POST", body: body, headers: { "Content-Type": "application/json" }, keepalive: true });
    } catch (e) { /* silencieux */ }
  };
  document.addEventListener("click", function (e) {
    var el = e.target.closest && e.target.closest("[data-track]");
    if (el) RF.track(el.getAttribute("data-track"), el.getAttribute("data-track-ref"));
  });

  /* ---- En-tête au scroll ---- */
  var header = $(".site-header");
  var onScroll = function () { if (header) header.classList.toggle("scrolled", window.scrollY > 24); };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---- Menu mobile + menu « Plus » ---- */
  var toggle = $(".nav-toggle");
  if (toggle) {
    toggle.addEventListener("click", function () {
      document.body.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", document.body.classList.contains("nav-open"));
    });
    $$(".nav-links a").forEach(function (a) {
      a.addEventListener("click", function () { document.body.classList.remove("nav-open"); });
    });
  }
  var more = $(".nav-more"), moreBtn = $(".nav-more-btn");
  if (more && moreBtn) {
    moreBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = more.classList.toggle("open");
      moreBtn.setAttribute("aria-expanded", open);
    });
    document.addEventListener("click", function () { more.classList.remove("open"); moreBtn.setAttribute("aria-expanded", "false"); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { more.classList.remove("open"); document.body.classList.remove("nav-open"); }
    });
  }

  /* ---- Lien actif dans le menu ---- */
  (function () {
    var path = location.pathname.slice(RF.base.length).replace(/^\/+/, "");
    var seg = path.split("/")[0].replace(/\.html$/, "");
    var map = {
      "": "home", index: "home", about: "about", ecosystem: "ecosystem", speaking: "speaking", media: "media",
      contact: "contact", programme: "programme", propositions: "programme", proposition: "programme",
      actualites: "actualites", article: "actualites", videos: "videos", video: "videos",
      "espace-citoyen": "espace-citoyen", newsletter: "newsletter", recherche: "",
    };
    map[RF.campaignPath] = "campagne";
    var key = map[seg];
    if (!key) return;
    $$('.nav-links [data-nav="' + key + '"]').forEach(function (a) { a.classList.add("active"); a.setAttribute("aria-current", "page"); });
    if ($('.nav-more-menu .active')) $(".nav-more-btn").classList.add("active");
  })();

  /* ---- Apparition au scroll ---- */
  var reveals = $$(".reveal");
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else reveals.forEach(function (el) { el.classList.add("in"); });

  /* ---- Compteurs animés ---- */
  var counters = $$("[data-count]");
  function runCounter(el) {
    var target = parseFloat(el.dataset.count || "0"), suffix = el.dataset.suffix || "", t0 = performance.now();
    (function step(t) {
      var p = Math.min((t - t0) / 1600, 1), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString("fr-FR") + suffix;
      if (p < 1) requestAnimationFrame(step);
    })(t0);
  }
  if ("IntersectionObserver" in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { cio.unobserve(en.target); runCounter(en.target); } });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { cio.observe(el); });
  } else counters.forEach(function (el) { el.textContent = el.dataset.count + (el.dataset.suffix || ""); });

  /* ---- Images : vraies balises <img> (lazy) avec repli élégant ----
     <div class="media" data-img="assets/img/x.jpg" data-alt="…"> … */
  $$(".media[data-img]").forEach(function (el) {
    var src = el.dataset.img;
    if (!src || el.querySelector("img")) return;
    var img = new Image();
    img.alt = el.dataset.alt || "";
    img.decoding = "async";
    if (!el.hasAttribute("data-eager")) img.loading = "lazy";
    else img.fetchPriority = "high";
    img.onload = function () { el.classList.add("has-img"); };
    img.onerror = function () { img.remove(); };
    img.src = RF.base + src.replace(/^\/+/, "");
    el.insertBefore(img, el.firstChild);
  });

  /* ---- Liens dynamiques ---- */
  $$("[data-link]").forEach(function (a) {
    var key = a.dataset.link;
    if (RF[key]) a.href = key === "email" ? "mailto:" + RF[key] : RF[key];
  });

  /* ---- Calendly (inline) ---- */
  var calBox = $("#calendly");
  if (calBox) {
    var div = document.createElement("div");
    div.className = "calendly-inline-widget";
    div.dataset.url = RF.calendly + "?hide_gdpr_banner=1&primary_color=c9a227";
    div.style.minWidth = "320px";
    div.style.height = "700px";
    calBox.appendChild(div);
    var s = document.createElement("script");
    s.src = "https://assets.calendly.com/assets/external/widget.js";
    s.async = true;
    calBox.appendChild(s);
  }

  /* ---- Formulaire de contact → e-mail pré-rempli ---- */
  var form = $("#contact-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var d = new FormData(form);
      var subject = encodeURIComponent("[" + d.get("topic") + "] " + d.get("name") + " — rooseveltforestal.com");
      var body = encodeURIComponent("Nom : " + d.get("name") + "\nOrganisation : " + (d.get("org") || "—") + "\nEmail : " + d.get("email") + "\n\n" + d.get("message"));
      window.location.href = "mailto:" + RF.email + "?subject=" + subject + "&body=" + body;
    });
  }

  /* ---- Année ---- */
  $$("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* ---- Page campagne : mesure de visite (+ origine QR) ---- */
  if (document.body.hasAttribute("data-track-view")) {
    var q = new URLSearchParams(location.search);
    RF.track(document.body.getAttribute("data-track-view"), q.get("s") === "qr" ? "qr" : "");
  }

  /* ---- « À la une » sur pages statiques (rempli depuis l'API si disponible) ---- */
  $$("[data-featured]").forEach(function (box) {
    var kind = box.getAttribute("data-featured");
    fetch(RF.base + "api/featured?kind=" + encodeURIComponent(kind), { headers: { Accept: "application/json" } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) {
        if (!data || !data.html) return;
        var target = $(".featured-slot", box) || box;
        target.innerHTML = data.html;
        box.hidden = false;
        $$(".reveal", box).forEach(function (el) { el.classList.add("in"); });
      })
      .catch(function () { /* backend absent : la section reste masquée */ });
  });
})();
