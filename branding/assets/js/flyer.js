/* ============================================================
   FLYER GENERATOR — « NOU PA NAN BEL DISKOU ANKO »
   100 % côté navigateur (Canvas). La photo n'est JAMAIS envoyée
   à un serveur et n'est stockée nulle part. Aucun compte requis.
   ============================================================ */
(function () {
  "use strict";
  var RF = (window.RF = window.RF || {});
  var t = function (k) { return RF.t ? RF.t(k) : k; };
  var $ = function (s) { return document.querySelector(s); };

  var CAMPAIGN_URL = "https://rooseveltforestal.com/nou-pa-nan-bel-diskou-anko";
  var FORMATS = { story: { w: 1080, h: 1920 }, post: { w: 1080, h: 1350 }, square: { w: 1080, h: 1080 } };
  var C = { navy: "#060f33", navy2: "#0a1a55", red: "#d21034", red2: "#ef2b4c", white: "#ffffff", flagBlue: "#00209f" };
  var MAX_BYTES = 15 * 1024 * 1024, MAX_EDGE = 2200;

  var S = { name: "", fmt: "story", qr: true, photo: null, zoom: 1, ox: 0, oy: 0 };
  var cv = $("#flyer"); if (!cv) return;
  var ctx = cv.getContext("2d");
  var hot = $("#pv-hot"), msgEl = $("#msg"), qrMatrix = null, exported = false, raf = 0;

  /* ---------------------------------------------------------------- utilitaires */
  function setMsg(text, kind) { msgEl.textContent = text || ""; msgEl.className = "msg" + (kind ? " " + kind : ""); }
  function key(k, kind) { setMsg(t(k), kind); }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function cleanName(v) { return (v || "").replace(/[\u0000-\u001f\u007f]/g, "").replace(/\s+/g, " ").trim().slice(0, 40); }
  function slug(v) { return (v || "flyer").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 30) || "flyer"; }
  function isIOS() { return /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1); }
  function isInApp() { return /FBAN|FBAV|FB_IAB|Instagram|WhatsApp|TikTok|musical_ly|Line\/|Snapchat|MicroMessenger/i.test(navigator.userAgent); }

  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function font(weight, size, fam) { ctx.font = weight + " " + Math.round(size) + "px " + (fam || "Inter") + ", Arial, sans-serif"; }
  function measure(text, weight, fam) { font(weight, 100, fam); return ctx.measureText(text).width; }
  function fit(text, weight, fam, maxW, maxSize, minSize) { var s = (100 * maxW) / measure(text, weight, fam); return clamp(Math.floor(Math.min(s, maxSize)), minSize || 8, maxSize); }

  /* ---------------------------------------------------------------- mise en page */
  function layout(fmt) {
    var f = FORMATS[fmt];
    if (fmt === "story") return { W: f.w, H: f.h, name: { x: 70, y: 170, w: 940, h: 170 }, photo: { x: 100, y: 378, w: 880, h: 720, r: 36 }, title: { x: 70, y: 1140, w: 940, h: 490, align: "center" }, sec: { x: 70, y: 1644, w: 940, size: 52, align: "center" }, sig: { x: 96, y: 1766 }, qr: { size: 150, x: 830, y: 1728 }, stripe: 14 };
    if (fmt === "post") return { W: f.w, H: f.h, name: { x: 70, y: 44, w: 940, h: 130 }, photo: { x: 100, y: 204, w: 880, h: 480, r: 32 }, title: { x: 70, y: 720, w: 940, h: 346, align: "center" }, sec: { x: 70, y: 1092, w: 940, size: 44, align: "center" }, sig: { x: 96, y: 1196 }, qr: { size: 124, x: 860, y: 1198 }, stripe: 12 };
    return { W: f.w, H: f.h, name: { x: 60, y: 44, w: 960, h: 100 }, photo: { x: 60, y: 172, w: 420, h: 690, r: 30 }, title: { x: 520, y: 172, w: 500, h: 420, align: "left" }, sec: { x: 520, y: 628, w: 500, size: 42, align: "left", wrap: true }, sig: { x: 66, y: 950 }, qr: { size: 140, x: 880, y: 908 }, stripe: 12 };
  }

  /* ---------------------------------------------------------------- dessin */
  function drawBackground(L) {
    var g = ctx.createLinearGradient(0, 0, 0, L.H); g.addColorStop(0, C.navy2); g.addColorStop(1, "#030818");
    ctx.fillStyle = g; ctx.fillRect(0, 0, L.W, L.H);
    var r1 = ctx.createRadialGradient(L.W, 0, 0, L.W, 0, L.W * 0.95); r1.addColorStop(0, "rgba(210,16,52,.55)"); r1.addColorStop(1, "rgba(210,16,52,0)");
    ctx.fillStyle = r1; ctx.fillRect(0, 0, L.W, L.H);
    var r2 = ctx.createRadialGradient(0, L.H, 0, 0, L.H, L.W * 0.9); r2.addColorStop(0, "rgba(0,32,159,.6)"); r2.addColorStop(1, "rgba(0,32,159,0)");
    ctx.fillStyle = r2; ctx.fillRect(0, 0, L.W, L.H);
    // liseré aux couleurs du drapeau
    ctx.fillStyle = C.flagBlue; ctx.fillRect(0, L.H - L.stripe, L.W / 2, L.stripe);
    ctx.fillStyle = C.red; ctx.fillRect(L.W / 2, L.H - L.stripe, L.W / 2, L.stripe);
  }

  function drawName(L) {
    var b = L.name, txt = cleanName(S.name).toUpperCase(), ph = !txt;
    if (ph) txt = t("c.name_ph").toUpperCase();
    ctx.fillStyle = ph ? "rgba(210,16,52,.55)" : C.red; rr(b.x, b.y, b.w, b.h, 20); ctx.fill();
    var maxW = b.w - 72, cx = b.x + b.w / 2, cy = b.y + b.h / 2;
    ctx.fillStyle = "#fff"; ctx.textAlign = "center"; ctx.textBaseline = "alphabetic";
    var s1 = fit(txt, 400, "Anton", maxW, b.h * 0.78, 18), words = txt.split(" ");
    var two = null;
    if (words.length > 1 && s1 < Math.max(64, b.h * 0.44)) {
      var best = null;
      for (var i = 1; i < words.length; i++) {
        var a = words.slice(0, i).join(" "), c = words.slice(i).join(" ");
        var wd = Math.max(measure(a, 400, "Anton"), measure(c, 400, "Anton"));
        if (!best || wd < best.wd) best = { a: a, c: c, wd: wd };
      }
      var s2 = Math.min(b.h * 0.42, Math.floor((100 * maxW) / best.wd));
      if (s2 > s1) two = { a: best.a, c: best.c, s: s2 };
    }
    if (two) {
      font(400, two.s, "Anton"); var cap = ctx.measureText("H").actualBoundingBoxAscent || two.s * 0.73;
      var gap = two.s * 0.16, bh = cap * 2 + gap, y1 = cy - bh / 2 + cap;
      ctx.fillText(two.a, cx, y1); ctx.fillText(two.c, cx, y1 + cap + gap);
    } else {
      font(400, s1, "Anton"); var cap1 = ctx.measureText("H").actualBoundingBoxAscent || s1 * 0.73;
      ctx.fillText(txt, cx, cy + cap1 / 2);
    }
  }

  function initials() {
    var n = cleanName(S.name); if (!n) return "";
    var w = n.split(" "); return ((w[0][0] || "") + (w.length > 1 ? w[w.length - 1][0] || "" : "")).toUpperCase();
  }
  function drawPhoto(L) {
    var p = L.photo;
    ctx.fillStyle = C.red; rr(p.x + 18, p.y + 18, p.w, p.h, p.r); ctx.fill();
    ctx.fillStyle = "#fff"; rr(p.x - 10, p.y - 10, p.w + 20, p.h + 20, p.r + 8); ctx.fill();
    ctx.save(); rr(p.x, p.y, p.w, p.h, p.r); ctx.clip();
    if (S.photo) {
      var iw = S.photo.width, ih = S.photo.height, sc = Math.max(p.w / iw, p.h / ih) * S.zoom, dw = iw * sc, dh = ih * sc;
      var mx = (dw - p.w) / 2, my = (dh - p.h) / 2;
      ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = "high";
      ctx.drawImage(S.photo, p.x + (p.w - dw) / 2 + S.ox * mx, p.y + (p.h - dh) / 2 + S.oy * my, dw, dh);
    } else {
      var g = ctx.createLinearGradient(p.x, p.y, p.x + p.w, p.y + p.h); g.addColorStop(0, "#1b3a9a"); g.addColorStop(1, "#0a1a55");
      ctx.fillStyle = g; ctx.fillRect(p.x, p.y, p.w, p.h);
      var ini = initials(), cx = p.x + p.w / 2, cy = p.y + p.h / 2;
      ctx.fillStyle = "rgba(255,255,255,.16)";
      if (ini) { ctx.textAlign = "center"; ctx.textBaseline = "middle"; font(400, Math.min(p.h * 0.5, p.w * 0.6), "Anton"); ctx.fillText(ini, cx, cy); }
      else { ctx.beginPath(); ctx.arc(cx, cy - p.h * 0.1, Math.min(p.w, p.h) * 0.17, 0, 7); ctx.fill(); ctx.beginPath(); ctx.ellipse(cx, p.y + p.h * 0.88, Math.min(p.w, p.h) * 0.38, p.h * 0.28, 0, Math.PI, 0); ctx.fill(); }
    }
    ctx.restore();
  }

  function drawTitle(L) {
    var b = L.title, lines = ["NOU PA NAN", "BEL DISKOU", "ANKO"], fam = "Anton";
    var w1 = Math.max(measure(lines[0], 400, fam), measure(lines[1], 400, fam)), w3 = measure(lines[2], 400, fam);
    var S1 = Math.min(230, (100 * b.w) / w1), S2 = Math.min((100 * b.w) / w3, S1 * 1.38);
    function cap(s) { font(400, s, fam); return ctx.measureText("H").actualBoundingBoxAscent || s * 0.73; }
    function total(a, c) { return cap(a) * 2 + cap(c) + a * 0.15 * 2; }
    var tt = total(S1, S2); if (tt > b.h) { var k = b.h / tt; S1 *= k; S2 *= k; tt = total(S1, S2); }
    var x = b.align === "center" ? b.x + b.w / 2 : b.x, y = b.y + (b.h - tt) / 2, gap = S1 * 0.15;
    ctx.textAlign = b.align; ctx.textBaseline = "alphabetic";
    ctx.fillStyle = "#fff"; font(400, S1, fam); y += cap(S1); ctx.fillText(lines[0], x, y);
    y += gap + cap(S1); ctx.fillText(lines[1], x, y);
    ctx.fillStyle = C.red2; font(400, S2, fam); y += gap + cap(S2); ctx.fillText(lines[2], x, y);
  }

  function drawSecondary(L) {
    var s = L.sec, text = "Mwen anvi viv byen nan peyi m.";
    ctx.fillStyle = "rgba(255,255,255,.95)"; ctx.textAlign = s.align; ctx.textBaseline = "alphabetic";
    var x = s.align === "center" ? s.x + s.w / 2 : s.x;
    if (s.wrap) {
      font(600, s.size, "Inter"); var a = "Mwen anvi viv", c = "byen nan peyi m.";
      ctx.fillText(a, x, s.y + s.size); ctx.fillText(c, x, s.y + s.size * 2.25);
    } else {
      var sz = fit(text, 600, "Inter", s.w, s.size, 20); font(600, sz, "Inter"); ctx.fillText(text, x, s.y + sz);
    }
  }

  function getQR() {
    if (qrMatrix) return qrMatrix;
    if (typeof qrcode !== "function") return null;
    try { var q = qrcode(0, "M"); q.addData(CAMPAIGN_URL + "?s=qr"); q.make(); var n = q.getModuleCount(), m = []; for (var r = 0; r < n; r++) { m[r] = []; for (var c = 0; c < n; c++) m[r][c] = q.isDark(r, c); } qrMatrix = m; } catch (e) { qrMatrix = null; }
    return qrMatrix;
  }
  function drawSignature(L) {
    var s = L.sig;
    ctx.fillStyle = C.red; ctx.fillRect(s.x - 24, s.y, 8, 78);
    ctx.textAlign = "left"; ctx.textBaseline = "alphabetic";
    ctx.fillStyle = "#fff"; font(700, 40, "Inter"); ctx.fillText("Roosevelt Forestal", s.x, s.y + 34);
    ctx.fillStyle = "rgba(255,255,255,.78)"; font(500, 30, "Inter"); ctx.fillText("rooseveltforestal.com", s.x, s.y + 74);
    var q = getQR(); if (!S.qr || !q) return;
    var pad = 12, size = L.qr.size, box = size + pad * 2;
    ctx.fillStyle = "#fff"; rr(L.qr.x - pad, L.qr.y - pad, box, box, 14); ctx.fill();
    var cell = size / q.length; ctx.fillStyle = C.navy;
    for (var r = 0; r < q.length; r++) for (var c = 0; c < q.length; c++) if (q[r][c]) ctx.fillRect(Math.floor(L.qr.x + c * cell), Math.floor(L.qr.y + r * cell), Math.ceil(cell), Math.ceil(cell));
  }

  function paint(target, fmt, k) {
    var L = layout(fmt), prev = ctx;
    target.width = Math.round(L.W * k); target.height = Math.round(L.H * k);
    ctx = target.getContext("2d");
    ctx.setTransform(k, 0, 0, k, 0, 0);
    drawBackground(L); drawName(L); drawPhoto(L); drawTitle(L); drawSecondary(L); drawSignature(L);
    ctx = prev;
    return L;
  }

  /* ---------------------------------------------------------------- aperçu */
  function previewScale() { return (window.devicePixelRatio || 1) >= 2 ? 0.62 : 0.5; }
  function render() {
    raf = 0;
    var L = paint(cv, S.fmt, previewScale());
    cv.setAttribute("aria-label", t("c.preview_alt") + (cleanName(S.name) ? " — " + cleanName(S.name) : ""));
    var p = L.photo;
    hot.style.left = (p.x / L.W) * 100 + "%"; hot.style.top = (p.y / L.H) * 100 + "%";
    hot.style.width = (p.w / L.W) * 100 + "%"; hot.style.height = (p.h / L.H) * 100 + "%";
    hot.classList.toggle("on", !!S.photo);
    hot.style.pointerEvents = S.photo ? "auto" : "none";
  }
  function schedule() { if (!raf) raf = requestAnimationFrame(render); }

  /* ---------------------------------------------------------------- photo */
  function readMagic(file) {
    return file.slice(0, 12).arrayBuffer().then(function (buf) {
      var b = new Uint8Array(buf);
      var jpg = b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff;
      var png = b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4e && b[3] === 0x47;
      var webp = b[0] === 0x52 && b[1] === 0x49 && b[2] === 0x46 && b[3] === 0x46 && b[8] === 0x57 && b[9] === 0x45 && b[10] === 0x42 && b[11] === 0x50;
      return jpg || png || webp;
    });
  }
  function decode(file) {
    if (window.createImageBitmap) return createImageBitmap(file, { imageOrientation: "from-image" }).catch(function () { return viaImg(file); });
    return viaImg(file);
  }
  function viaImg(file) {
    return new Promise(function (res, rej) {
      var u = URL.createObjectURL(file), im = new Image();
      im.onload = function () { URL.revokeObjectURL(u); res(im); };
      im.onerror = function () { URL.revokeObjectURL(u); rej(new Error("decode")); };
      im.src = u;
    });
  }
  function downscale(src) {
    var w = src.width, h = src.height, k = Math.min(1, MAX_EDGE / Math.max(w, h));
    var c = document.createElement("canvas"); c.width = Math.round(w * k); c.height = Math.round(h * k);
    var x = c.getContext("2d"); x.imageSmoothingQuality = "high"; x.drawImage(src, 0, 0, c.width, c.height);
    if (src.close) src.close();
    return c;
  }
  function releasePhoto() { if (S.photo && S.photo.width) { S.photo.width = 1; S.photo.height = 1; } S.photo = null; S.zoom = 1; S.ox = 0; S.oy = 0; }
  function loadFile(file) {
    if (!file) return;
    setMsg("");
    if (file.size > MAX_BYTES) return key("c.err_size", "err");
    readMagic(file).then(function (ok) {
      if (!ok) { key("c.err_type", "err"); return null; }
      return decode(file).then(function (img) { releasePhoto(); S.photo = downscale(img); syncPhotoUI(); schedule(); var pv = $(".pv"); if (pv && pv.getBoundingClientRect().top < 0) pv.scrollIntoView({ behavior: "smooth", block: "center" }); });
    }).catch(function () { key("c.err_load", "err"); });
  }
  function syncPhotoUI() {
    $("#photo-ctl").classList.toggle("on", !!S.photo);
    $("#zoom").value = S.zoom; $("#px").value = S.ox; $("#py").value = S.oy;
    $("#no-photo-note").hidden = !!S.photo;
  }

  /* ---------------------------------------------------------------- glisser / pincer */
  var ptrs = {}, last = null, pinch0 = 0, zoom0 = 1;
  function dims() { var L = layout(S.fmt), r = cv.getBoundingClientRect(); return { L: L, f: L.W / r.width }; }
  function overflow() {
    var L = layout(S.fmt), p = L.photo, sc = Math.max(p.w / S.photo.width, p.h / S.photo.height) * S.zoom;
    return { x: (S.photo.width * sc - p.w) / 2, y: (S.photo.height * sc - p.h) / 2 };
  }
  hot.addEventListener("pointerdown", function (e) {
    if (!S.photo) return; hot.setPointerCapture(e.pointerId); ptrs[e.pointerId] = { x: e.clientX, y: e.clientY };
    var ids = Object.keys(ptrs);
    if (ids.length === 2) { var a = ptrs[ids[0]], b = ptrs[ids[1]]; pinch0 = Math.hypot(a.x - b.x, a.y - b.y); zoom0 = S.zoom; }
    hot.classList.add("drag"); last = { x: e.clientX, y: e.clientY };
  });
  hot.addEventListener("pointermove", function (e) {
    if (!ptrs[e.pointerId] || !S.photo) return;
    ptrs[e.pointerId] = { x: e.clientX, y: e.clientY };
    var ids = Object.keys(ptrs);
    if (ids.length >= 2) {
      var a = ptrs[ids[0]], b = ptrs[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinch0) { S.zoom = clamp(zoom0 * (d / pinch0), 1, 4); syncPhotoUI(); schedule(); }
      return;
    }
    var f = dims().f, o = overflow(), dx = (e.clientX - last.x) * f, dy = (e.clientY - last.y) * f;
    if (o.x > 0) S.ox = clamp(S.ox + dx / o.x, -1, 1);
    if (o.y > 0) S.oy = clamp(S.oy + dy / o.y, -1, 1);
    last = { x: e.clientX, y: e.clientY }; $("#px").value = S.ox; $("#py").value = S.oy; schedule();
  });
  function up(e) { delete ptrs[e.pointerId]; pinch0 = 0; if (!Object.keys(ptrs).length) hot.classList.remove("drag"); }
  hot.addEventListener("pointerup", up); hot.addEventListener("pointercancel", up);
  hot.addEventListener("wheel", function (e) { if (!S.photo) return; e.preventDefault(); S.zoom = clamp(S.zoom + (e.deltaY < 0 ? 0.08 : -0.08), 1, 4); syncPhotoUI(); schedule(); }, { passive: false });

  /* ---------------------------------------------------------------- export */
  function toBlob(fmt) {
    return new Promise(function (res, rej) {
      var c = document.createElement("canvas"); paint(c, fmt, 1);
      c.toBlob(function (b) { b ? res(b) : rej(new Error("blob")); }, "image/png");
    });
  }
  function needName() {
    if (cleanName(S.name)) return false;
    key("c.need_name", "err"); $("#f-name").focus(); return true;
  }
  function revealShare() { $("#share").classList.add("on"); $(".cm-next-wrap") && $(".cm-next-wrap").classList.add("hl"); }
  function trackFirst() { if (!exported) { exported = true; RF.track && RF.track("flyer_generated"); } }

  function saveOrShow(blob, fmt) {
    var name = "nou-pa-nan-bel-diskou-anko-" + slug(S.name) + "-" + fmt + ".png";
    if (isIOS() || isInApp()) {
      var u = URL.createObjectURL(blob);
      var m = RF.modal("<h3>" + t("c.ios_title") + "</h3><p>" + t("c.ios_body") + '</p><img alt="" src="' + u + '">', "rf-modal-flyer");
      m.el.addEventListener("click", function (e) { if (e.target === m.el || e.target.closest(".rf-modal-x")) URL.revokeObjectURL(u); });
    } else RF.saveBlob(blob, name);
    return name;
  }
  function download(fmt) {
    if (needName()) return Promise.resolve(false);
    fmt = fmt || S.fmt; key("c.generating");
    return toBlob(fmt).then(function (blob) {
      trackFirst(); RF.track && RF.track("flyer_download", fmt);
      saveOrShow(blob, fmt); key("c.done", "ok"); revealShare(); return blob;
    }).catch(function () { key("c.dl_fail", "err"); return false; });
  }

  /* ---------------------------------------------------------------- partage */
  function shareText() { return t("c.share_text"); }
  function openWin(u) { window.open(u, "_blank", "noopener,noreferrer,width=640,height=560"); }
  function socialClick(net) {
    RF.track && RF.track("share_click", "campaign:" + net);
    var enc = encodeURIComponent;
    if (net === "whatsapp") return openWin("https://wa.me/?text=" + enc(shareText() + " " + CAMPAIGN_URL));
    if (net === "facebook") return openWin("https://www.facebook.com/sharer/sharer.php?u=" + enc(CAMPAIGN_URL));
    if (net === "x") return openWin("https://x.com/intent/post?text=" + enc(shareText()) + "&url=" + enc(CAMPAIGN_URL));
    var ig = net === "instagram";
    var m = RF.modal("<h3>" + t(ig ? "s.ig_title" : "s.tt_title") + "</h3><p>" + t(ig ? "s.ig_steps" : "s.tt_steps") + '</p><ol class="steps"><li>' + t("s.step_dl") + "</li><li>" + t(ig ? "s.step_ig" : "s.step_tt") + "</li><li>" + t("s.step_link") + '</li></ol><div class="modal-actions"><button class="btn btn-gold" data-a="dl">' + t(ig ? "c.ig" : "c.tt") + '</button><button class="btn btn-ghost" data-a="copy">' + t("s.copy") + '</button><a class="btn btn-ghost" target="_blank" rel="noopener" href="' + (ig ? "https://www.instagram.com/" : "https://www.tiktok.com/") + '">' + t(ig ? "s.open_ig" : "s.open_tt") + "</a></div>");
    m.el.addEventListener("click", function (e) {
      var a = e.target.closest("[data-a]"); if (!a) return;
      if (a.dataset.a === "copy") RF.copy(CAMPAIGN_URL).then(function () { RF.toast(t("c.copied")); }, function () { RF.toast(t("s.fail")); });
      if (a.dataset.a === "dl") { m.close(); download("story"); }
    });
  }
  function nativeShare() {
    if (needName()) return;
    key("c.generating");
    toBlob(S.fmt).then(function (blob) {
      var file = new File([blob], "nou-pa-nan-bel-diskou-anko.png", { type: "image/png" });
      var data = { files: [file], text: shareText(), url: CAMPAIGN_URL, title: "NOU PA NAN BEL DISKOU ANKO" };
      trackFirst(); RF.track && RF.track("share_click", "campaign:native");
      if (navigator.canShare && navigator.canShare({ files: [file] })) return navigator.share(data).then(function () { revealShare(); key("c.done", "ok"); });
      return navigator.share({ text: shareText(), url: CAMPAIGN_URL, title: data.title });
    }).catch(function (e) { if (e && e.name !== "AbortError") key("c.dl_fail", "err"); else setMsg(""); });
  }

  /* ---------------------------------------------------------------- init UI */
  function init() {
    var nameEl = $("#f-name");
    nameEl.addEventListener("input", function () { S.name = nameEl.value; if (cleanName(S.name)) $("#share").classList.add("on"); setMsg(""); schedule(); });
    var file = $("#f-photo"), cam = $("#f-camera");
    [file, cam].forEach(function (el) { el.addEventListener("change", function () { loadFile(el.files[0]); el.value = ""; }); });
    $("#btn-photo").addEventListener("click", function () { file.click(); });
    var camBtn = $("#btn-camera");
    if (window.matchMedia && matchMedia("(pointer: coarse)").matches) camBtn.hidden = false;
    camBtn.addEventListener("click", function () { cam.click(); });
    $("#btn-replace").addEventListener("click", function () { file.click(); });
    $("#btn-remove").addEventListener("click", function () { releasePhoto(); syncPhotoUI(); schedule(); });
    $("#zoom").addEventListener("input", function (e) { S.zoom = parseFloat(e.target.value); schedule(); });
    $("#px").addEventListener("input", function (e) { S.ox = parseFloat(e.target.value); schedule(); });
    $("#py").addEventListener("input", function (e) { S.oy = parseFloat(e.target.value); schedule(); });
    [].forEach.call(document.querySelectorAll('input[name="fmt"]'), function (r) { r.addEventListener("change", function () { S.fmt = r.value; schedule(); }); });
    $("#qr").addEventListener("change", function (e) { S.qr = e.target.checked; schedule(); });
    $("#btn-dl").addEventListener("click", function () { download(); });
    $("#btn-copy").addEventListener("click", function () { RF.track && RF.track("share_click", "campaign:copy"); RF.copy(CAMPAIGN_URL).then(function () { RF.toast(t("c.copied")); }, function () { RF.toast(t("s.fail")); }); });
    var nat = $("#btn-native"); if (navigator.share) nat.hidden = false; nat.addEventListener("click", nativeShare);
    [].forEach.call(document.querySelectorAll("#share .sh"), function (b) {
      b.innerHTML = RF.icon ? RF.icon(b.getAttribute("data-net")) : "";
      b.addEventListener("click", function () { socialClick(b.getAttribute("data-net")); });
    });
    document.addEventListener("rf:lang", schedule);
    window.addEventListener("pagehide", releasePhoto);
    syncPhotoUI(); render();
    var ready = document.fonts && document.fonts.load ? Promise.all([document.fonts.load("400 100px Anton"), document.fonts.load("600 40px Inter"), document.fonts.load("700 40px Inter")]) : Promise.resolve();
    ready.then(schedule, schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
    RF._flyer = { S: S, paint: paint, loadFile: loadFile, toBlob: toBlob, set: function (k, v) { S[k] = v; schedule(); } }; // utilisé par les tests
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
