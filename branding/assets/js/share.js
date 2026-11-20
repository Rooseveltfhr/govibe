/* ============================================================
   Partage social — boutons avec logos officiels (SVG), copier le lien,
   partage natif, visuel de partage (Canvas) pour Instagram / TikTok.
   Honnête : aucun réseau n'est « publié automatiquement ».
   Markup attendu :
   <div class="share-bar" data-url="…" data-title="…" data-text="…" data-image="…" data-ref="article:slug">
   ============================================================ */
(function () {
  "use strict";
  var RF = (window.RF = window.RF || {});
  var t = function (k) { return RF.t ? RF.t(k) : k; };

  /* Icônes officielles (simple-icons, CC0) */
  var ICON = {
    facebook: "M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z",
    whatsapp: "M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z",
    x: "M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z",
    tiktok: "M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z",
    instagram: "M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077",
    link: "M3.9 12a3.1 3.1 0 0 1 3.1-3.1h4V7H7a5 5 0 0 0 0 10h4v-1.9H7A3.1 3.1 0 0 1 3.9 12zM8 13h8v-2H8v2zm9-6h-4v1.9h4a3.1 3.1 0 0 1 0 6.2h-4V17h4a5 5 0 0 0 0-10z",
    dl: "M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z",
    share: "M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"
  };
  var svg = function (n) { return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' + ICON[n] + '"/></svg>'; };
  RF.icon = svg;

  var LABEL = { facebook: "Facebook", whatsapp: "WhatsApp", x: "X", instagram: "Instagram", tiktok: "TikTok" };

  /* ---------- Copier ---------- */
  RF.copy = function (text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (res, rej) {
      var ta = document.createElement("textarea");
      ta.value = text; ta.setAttribute("readonly", ""); ta.style.cssText = "position:fixed;opacity:0;top:0";
      document.body.appendChild(ta); ta.select();
      try { document.execCommand("copy") ? res() : rej(); } catch (e) { rej(e); } finally { ta.remove(); }
    });
  };

  /* ---------- Modale simple ---------- */
  RF.modal = function (html, cls) {
    var m = document.createElement("div");
    m.className = "rf-modal " + (cls || "");
    m.setAttribute("role", "dialog"); m.setAttribute("aria-modal", "true");
    m.innerHTML = '<div class="rf-modal-box"><button class="rf-modal-x" aria-label="' + t("s.close") + '">×</button>' + html + "</div>";
    var close = function () { m.remove(); document.removeEventListener("keydown", esc); };
    var esc = function (e) { if (e.key === "Escape") close(); };
    m.addEventListener("click", function (e) { if (e.target === m || e.target.closest(".rf-modal-x")) close(); });
    document.addEventListener("keydown", esc);
    document.body.appendChild(m);
    var f = m.querySelector("button, a"); if (f) f.focus();
    return { el: m, close: close };
  };

  /* ---------- Visuel de partage (Canvas 1080×1350) ---------- */
  function wrapLines(ctx, text, maxW, maxLines) {
    var words = text.split(/\s+/), lines = [], cur = "";
    for (var i = 0; i < words.length; i++) {
      var test = cur ? cur + " " + words[i] : words[i];
      if (ctx.measureText(test).width > maxW && cur) { lines.push(cur); cur = words[i]; } else cur = test;
    }
    if (cur) lines.push(cur);
    if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, "") + "…"; }
    return lines;
  }
  RF.shareCard = function (opts) {
    var W = 1080, H = 1350, c = document.createElement("canvas"); c.width = W; c.height = H;
    var x = c.getContext("2d");
    function finish(img) {
      var g = x.createLinearGradient(0, 0, 0, H); g.addColorStop(0, "#07102e"); g.addColorStop(1, "#0b0b0c");
      x.fillStyle = g; x.fillRect(0, 0, W, H);
      if (img) {
        var r = Math.max(W / img.width, 760 / img.height), w = img.width * r, h = img.height * r;
        x.save(); x.beginPath(); x.rect(0, 0, W, 760); x.clip(); x.drawImage(img, (W - w) / 2, (760 - h) / 2, w, h);
        var fg = x.createLinearGradient(0, 380, 0, 760); fg.addColorStop(0, "rgba(7,16,46,0)"); fg.addColorStop(1, "rgba(7,16,46,1)");
        x.fillStyle = fg; x.fillRect(0, 380, W, 380); x.restore();
      }
      x.fillStyle = "#d21034"; x.fillRect(70, 700, 120, 10);
      x.fillStyle = "#fff"; x.font = "88px Anton, Impact, sans-serif"; x.textBaseline = "top";
      var lines = wrapLines(x, (opts.title || "").toUpperCase(), W - 140, 5);
      lines.forEach(function (l, i) { x.fillText(l, 70, 740 + i * 100); });
      x.fillStyle = "rgba(255,255,255,.75)"; x.font = "600 34px Inter, Arial, sans-serif";
      x.fillText("Roosevelt Forestal", 70, H - 130);
      x.fillStyle = "#e8c86a"; x.font = "500 30px Inter, Arial, sans-serif";
      x.fillText("rooseveltforestal.com", 70, H - 80);
      c.toBlob(function (b) { opts.done(b); }, "image/png");
    }
    var go = function () {
      if (!opts.image) return finish(null);
      var im = new Image(); im.crossOrigin = "anonymous";
      im.onload = function () { finish(im); }; im.onerror = function () { finish(null); };
      im.src = opts.image;
    };
    (document.fonts && document.fonts.load ? document.fonts.load("88px Anton") : Promise.resolve()).then(go, go);
  };
  RF.saveBlob = function (blob, name) {
    var a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = name;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
  };

  /* ---------- Construction des barres de partage ---------- */
  function build(bar) {
    if (bar.dataset.built) return; bar.dataset.built = "1";
    var nets = (bar.dataset.nets || "facebook,whatsapp,x,instagram,tiktok").split(",");
    var h = "";
    nets.forEach(function (n) {
      h += '<button type="button" class="sh sh-' + n + '" data-net="' + n + '" data-tip="' + LABEL[n] + '" aria-label="' + t("s.share") + " — " + LABEL[n] + '">' + svg(n) + "</button>";
    });
    h += '<button type="button" class="sh sh-copy" data-net="copy" data-tip="' + t("s.copy") + '" aria-label="' + t("s.copy") + '">' + svg("link") + "</button>";
    if (bar.dataset.card !== "0") h += '<button type="button" class="sh sh-dl" data-net="download" data-tip="' + t("s.download") + '" aria-label="' + t("s.download") + '">' + svg("dl") + "</button>";
    if (navigator.share) h += '<button type="button" class="sh sh-native" data-net="native" data-tip="' + t("s.native") + '" aria-label="' + t("s.native") + '">' + svg("share") + "</button>";
    bar.innerHTML = h;
  }

  function onShare(bar, net) {
    var url = bar.dataset.url || location.href.split("#")[0];
    var title = bar.dataset.title || document.title;
    var text = bar.dataset.text || title;
    var ref = bar.dataset.ref || "";
    RF.track && RF.track("share_click", net + (ref ? "|" + ref : ""));
    var enc = encodeURIComponent;
    var open = function (u) { window.open(u, "_blank", "noopener,noreferrer,width=640,height=560"); };

    if (net === "whatsapp") return open("https://wa.me/?text=" + enc(title + "\n" + (text !== title ? text + "\n" : "") + url));
    if (net === "facebook") return open("https://www.facebook.com/sharer/sharer.php?u=" + enc(url));
    if (net === "x") return open("https://x.com/intent/post?text=" + enc(title) + "&url=" + enc(url));
    if (net === "copy") return RF.copy(url).then(function () { RF.toast(t("s.copied")); }, function () { RF.toast(t("s.fail")); });
    if (net === "native") return navigator.share({ title: title, text: text, url: url }).catch(function () {});
    if (net === "download") return makeCard(bar, title, function (b) { RF.saveBlob(b, (ref.split(":")[1] || "partage") + ".png"); RF.toast("✓"); });
    if (net === "instagram" || net === "tiktok") {
      var ig = net === "instagram";
      var m = RF.modal(
        "<h3>" + t(ig ? "s.ig_title" : "s.tt_title") + "</h3><p>" + t(ig ? "s.ig_steps" : "s.tt_steps") + "</p><ol class=\"steps\"><li>" + t("s.step_dl") + "</li><li>" + t(ig ? "s.step_ig" : "s.step_tt") + "</li><li>" + t("s.step_link") + "</li></ol>" +
        '<div class="modal-actions"><button class="btn btn-gold" data-a="dl">' + t("s.download") + '</button><button class="btn btn-ghost" data-a="copy">' + t("s.copy") + '</button><a class="btn btn-ghost" target="_blank" rel="noopener" href="' + (ig ? "https://www.instagram.com/" : "https://www.tiktok.com/") + '">' + t(ig ? "s.open_ig" : "s.open_tt") + "</a></div>"
      );
      m.el.addEventListener("click", function (e) {
        var a = e.target.closest("[data-a]"); if (!a) return;
        if (a.dataset.a === "copy") RF.copy(url).then(function () { RF.toast(t("s.copied")); }, function () { RF.toast(t("s.fail")); });
        if (a.dataset.a === "dl") makeCard(bar, title, function (b) { RF.saveBlob(b, (ref.split(":")[1] || "partage") + ".png"); });
      });
    }
  }
  function makeCard(bar, title, cb) {
    RF.shareCard({ title: title, image: bar.dataset.image, done: function (b) { if (b) cb(b); else RF.toast(t("s.fail")); } });
  }

  document.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest(".share-bar .sh");
    if (b) onShare(b.closest(".share-bar"), b.getAttribute("data-net"));
  });
  function init() { [].forEach.call(document.querySelectorAll(".share-bar"), build); }
  RF.initShare = init;
  document.addEventListener("rf:lang", function () { [].forEach.call(document.querySelectorAll(".share-bar"), function (b) { delete b.dataset.built; build(b); }); });
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
