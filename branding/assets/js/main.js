/* ============================================================
   ROOSEVELT FORESTAL — Personal Brand
   Config + interactions (no dependencies)
   ============================================================ */

/* ---- CONFIG : mettre à jour ici uniquement ---- */
const RF = {
  calendly: "https://calendly.com/rooseveltforestal", // lien officiel confirmé
  whatsapp: "https://wa.me/message/5FJZXWSPZRJHB1",
  facebook: "https://www.facebook.com/share/1ELxgXJDTx/",
  linkedin: "https://www.linkedin.com/in/roosevelt-forestal-530864145",
  email: "mr@rooseveltforestal.com", // email public officiel
  site: "https://rooseveltforestal.com",
};

/* ---- Header on scroll ---- */
const header = document.querySelector(".site-header");
const onScroll = () => header && header.classList.toggle("scrolled", window.scrollY > 24);
window.addEventListener("scroll", onScroll, { passive: true });
onScroll();

/* ---- Mobile nav ---- */
const toggle = document.querySelector(".nav-toggle");
if (toggle) {
  toggle.addEventListener("click", () => {
    document.body.classList.toggle("nav-open");
    toggle.setAttribute("aria-expanded", document.body.classList.contains("nav-open"));
  });
  document.querySelectorAll(".nav-links a").forEach((a) =>
    a.addEventListener("click", () => document.body.classList.remove("nav-open"))
  );
}

/* ---- Reveal on scroll ---- */
const io = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add("in");
        io.unobserve(e.target);
      }
    });
  },
  { threshold: 0.12 }
);
document.querySelectorAll(".reveal").forEach((el) => io.observe(el));

/* ---- Animated counters ---- */
const counterIO = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      counterIO.unobserve(el);
      const target = parseFloat(el.dataset.count || "0");
      const suffix = el.dataset.suffix || "";
      const dur = 1600;
      const t0 = performance.now();
      const step = (t) => {
        const p = Math.min((t - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased).toLocaleString("en-US") + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  },
  { threshold: 0.4 }
);
document.querySelectorAll("[data-count]").forEach((el) => counterIO.observe(el));

/* ---- Image loader with premium fallback ----
   <div class="media" data-img="assets/img/hero.jpg"><div class="ph"><span>RF</span></div></div>
   Si l'image existe → affichée. Sinon → monogramme or sur noir (aucune icône cassée). */
document.querySelectorAll(".media[data-img]").forEach((el) => {
  const src = el.dataset.img;
  if (!src) return;
  const probe = new Image();
  probe.onload = () => {
    el.style.backgroundImage = `url("${src}")`;
    el.classList.add("has-img");
  };
  probe.src = src;
});

/* ---- Liens dynamiques depuis la config ---- */
document.querySelectorAll("[data-link]").forEach((a) => {
  const key = a.dataset.link;
  if (RF[key]) a.href = key === "email" ? `mailto:${RF[key]}` : RF[key];
});

/* ---- Calendly (inline embed, chargé seulement si un conteneur existe) ---- */
const calendlyBox = document.getElementById("calendly");
if (calendlyBox) {
  const div = document.createElement("div");
  div.className = "calendly-inline-widget";
  div.dataset.url = RF.calendly + "?hide_gdpr_banner=1&primary_color=c9a227";
  div.style.minWidth = "320px";
  div.style.height = "700px";
  calendlyBox.appendChild(div);
  const s = document.createElement("script");
  s.src = "https://assets.calendly.com/assets/external/widget.js";
  s.async = true;
  calendlyBox.appendChild(s);
}

/* ---- Formulaire contact → ouvre l'email pré-rempli ---- */
const form = document.getElementById("contact-form");
if (form) {
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const d = new FormData(form);
    const subject = encodeURIComponent(`[${d.get("topic")}] ${d.get("name")} — rooseveltforestal.com`);
    const body = encodeURIComponent(
      `Name: ${d.get("name")}\nOrganization: ${d.get("org") || "—"}\nEmail: ${d.get("email")}\n\n${d.get("message")}`
    );
    window.location.href = `mailto:${RF.email}?subject=${subject}&body=${body}`;
  });
}

/* ---- Année courante ---- */
document.querySelectorAll("[data-year]").forEach((el) => (el.textContent = new Date().getFullYear()));
