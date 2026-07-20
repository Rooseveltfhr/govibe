/* FINPO 2026 — interactions front (vanilla JS, sans dépendance) */
(function () {
  'use strict';

  /* Navbar : fond au scroll */
  const nav = document.querySelector('.fp-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    nav.addEventListener('show.bs.collapse', () => nav.classList.add('is-open'));
    nav.addEventListener('hidden.bs.collapse', () => nav.classList.remove('is-open'));
  }

  /* Thème sombre / clair */
  const themeBtn = document.getElementById('fp-theme-toggle');
  const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('fp-theme', theme);
    if (themeBtn) themeBtn.textContent = theme === 'dark' ? '☀️' : '🌙';
  };
  applyTheme(localStorage.getItem('fp-theme') || 'dark');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      applyTheme(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    });
  }

  /* Grandes polices (accessibilité) */
  const fontBtn = document.getElementById('fp-font-toggle');
  if (localStorage.getItem('fp-large') === '1') document.documentElement.classList.add('fp-large-text');
  if (fontBtn) {
    fontBtn.addEventListener('click', () => {
      const on = document.documentElement.classList.toggle('fp-large-text');
      localStorage.setItem('fp-large', on ? '1' : '0');
    });
  }

  /* Rotation des mots du hero */
  const rotator = document.querySelector('.fp-rotator');
  if (rotator) {
    let words = [];
    try { words = JSON.parse(rotator.dataset.words || '[]'); } catch (e) { /* ignore */ }
    if (words.length) {
      let wordIdx = 0, charIdx = 0, deleting = false;
      const tick = () => {
        const word = words[wordIdx];
        charIdx += deleting ? -1 : 1;
        rotator.textContent = word.slice(0, charIdx);
        let delay = deleting ? 45 : 95;
        if (!deleting && charIdx === word.length) { delay = 1600; deleting = true; }
        else if (deleting && charIdx === 0) { deleting = false; wordIdx = (wordIdx + 1) % words.length; delay = 300; }
        setTimeout(tick, delay);
      };
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        rotator.textContent = words[0];
      } else {
        tick();
      }
    }
  }

  /* Compte à rebours */
  document.querySelectorAll('[data-countdown]').forEach((el) => {
    const target = new Date(el.dataset.countdown).getTime();
    const cells = {
      d: el.querySelector('[data-cd="d"]'),
      h: el.querySelector('[data-cd="h"]'),
      m: el.querySelector('[data-cd="m"]'),
      s: el.querySelector('[data-cd="s"]'),
    };
    const pad = (n) => String(n).padStart(2, '0');
    const update = () => {
      const diff = Math.max(0, target - Date.now());
      const days = Math.floor(diff / 86400000);
      const hours = Math.floor(diff / 3600000) % 24;
      const mins = Math.floor(diff / 60000) % 60;
      const secs = Math.floor(diff / 1000) % 60;
      if (cells.d) cells.d.textContent = pad(days);
      if (cells.h) cells.h.textContent = pad(hours);
      if (cells.m) cells.m.textContent = pad(mins);
      if (cells.s) cells.s.textContent = pad(secs);
    };
    update();
    setInterval(update, 1000);
  });

  /* Apparition au scroll + compteurs animés */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');

      entry.target.querySelectorAll('[data-count]').forEach((counter) => {
        if (counter.dataset.done) return;
        counter.dataset.done = '1';
        const end = parseInt(counter.dataset.count, 10) || 0;
        const suffix = counter.dataset.suffix || '';
        const duration = 1800;
        const start = performance.now();
        const step = (now) => {
          const progress = Math.min(1, (now - start) / duration);
          const eased = 1 - Math.pow(1 - progress, 3);
          counter.textContent = Math.round(end * eased).toLocaleString('fr-FR') + suffix;
          if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      });

      io.unobserve(entry.target);
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.reveal, .fp-stat-row').forEach((el) => io.observe(el));

  /* Enregistrement du service worker (PWA) */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => { /* hors-ligne indisponible */ });
    });
  }
})();
