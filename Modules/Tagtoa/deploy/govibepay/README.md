# GOVIBEPAY — Fusion app.govibepay.com → govibepay.com

Objektif: fè **yon sèl sit**. WordPress (vitrin aktyèl sou govibepay.com) sispann
sèvi rasin lan; **app la** (ki te sou app.govibepay.com) vin sèvi dirèkteman sou
`https://govibepay.com/`, epi `app.govibepay.com` vin **vid** (redireksyon 301 vè rasin).
Yon **paj akèy GoVibePay** (statique, otonòm — `landing.html`) ranplase paj home nan.

Tout bagay fèt ak workflow GitHub Actions: **Actions → « GOVIBEPAY — Fusion app.govibepay.com → govibepay.com »**
(li itilize menm sekrè SSH ak deploy TAGTOA a: `VPS_SSH_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PORT`).

## Lòd rekòmande

1. **`inspect`** (defo — okenn chanjman): li estrikti reyèl sèvè a
   (`$HOME/domains/govibepay.com`, docroot app la, vèsyon PHP, DocumentRoot DirectAdmin,
   kòd HTTP lokal). **Li rapò a anvan ou fè merge.**
2. **`merge`**: fè fusion an —
   - WordPress **sovgade** nan `public_html_wp_<dat>` (jamè efase);
   - app la sèvi sou rasin lan (si Laravel: app la soti nan docroot, front controller
     jenere + `php -l`, lyen `storage` refèt, `APP_URL=https://govibepay.com` nan `.env`
     ak backup, cache Laravel vide);
   - `app.govibepay.com` vide: `index.php` + `.htaccess` redireksyon 301
     (kondisyone sou `HTTP_HOST` — sèlman sou-domèn nan redirije);
   - `landing.html` enstale kòm paj akèy (`DirectoryIndex index.html index.php` —
     rasin `/` sèvi landing lan, tout lòt URL (`/login`, `/register`, …) ale nan app la);
   - makè `.govibepay-merge.meta` ekri (idempotans + rollback).
3. Verifye sit la (`https://govibepay.com/`, login, `https://app.govibepay.com` → 301).
4. Si pwoblèm: **`rollback`** — restore WordPress jan l te ye, remèt app la nan plas orijinal li,
   restore `.env` ak fichye ki te ekarte yo. Eta fusion an konsève nan `public_html_merged_<dat>`.

Mode **`landing`**: (re)enstale sèlman paj akèy la (apre yon chanjman nan `landing.html`),
san manyen anyen lòt. Li refize mache si fusion an poko fèt (pou pa kraze home WordPress la).

## Paj akèy la (`landing.html`)

- 100% otonòm (okenn asset ekstèn, pa bezwen build) — modle sou kontni reyèl
  sit govibepay.com lan: kat Visa & Mastercard vityèl/fizik, transfè, depo/retrè ajan,
  recharge Digicel & Natcom, faktir (EDH, DINEPA, …), peman QR, seksyon ajan.
- Koulè yo nan varyab CSS anlè fichye a (`--brand`, `--ink`, `--grad`) — fasil ajiste
  pou matche brand la egzakteman.
- Bouton yo pwente sou `/login` ak `/register`. **Si script la itilize lòt wout**
  (egz. `/user/login`), chanje `href` yo nan `landing.html` epi relanse mode `landing`.
- Makè `GOVIBEPAY-LANDING` nan fichye a idantifye vèsyon pa nou (yon `index.html`
  etranje sovgade anvan ranplasman).

## Pyèj pou verifye (nan rapò `inspect`)

- **Vèsyon PHP pa domèn**: nan DirectAdmin, govibepay.com (WordPress) ka sou yon vèsyon
  PHP diferan de app.govibepay.com. Anvan `merge`, mete selektè PHP govibepay.com nan
  menm vèsyon ak app la (Laravel 10 → PHP 8.1+), sinon rasin lan ap fatal error.
- **Docroot sou-domèn nan**: DirectAdmin mete docroot sou-domèn yo swa nan
  `domains/app.govibepay.com/public_html` (domèn apa), swa nan
  `domains/govibepay.com/public_html/app` (sou-domèn klasik). Workflow la detekte
  toude — men si `inspect` pa jwenn app la nan youn nan chemen sa yo, fè `merge` la
  **pa** kouri (li ap kanpe pwòp) epi voye rapò a ban mwen pou adapte workflow la.
- **Paj WordPress yo** (about, kontak, …) p ap disponib ankò apre fusion — se sa
  « yon sèl sit » vle di. Tout kontni WP rete entak nan backup la si ou bezwen l.
- **Cron/queue** app la (si genyen) pa deplase pa workflow la — si yon cron pwente sou
  ansyen chemen (`.../public_html/app/artisan`), mete l ajou apre merge
  (nouvo chemen: `domains/govibepay.com/govibepay-app/artisan` si app la te nan docroot WP a).

## Fichye

- `landing.html` — paj akèy GoVibePay (sous verite; modifye la, pa sou sèvè a).
- `.github/workflows/govibepay-merge.yml` — workflow inspect/merge/landing/rollback.
