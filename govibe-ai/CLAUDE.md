# CLAUDE.md — LOUVIA (GOVIBE AI)

> Memwa sou eta AKTYÈL `govibe-ai/`. Li fichye sa a anvan ou touche okenn
> fichye nan dosye sa a. Mete l ajou lè yon bagay enpòtan chanje.
>
> CLAUDE.md nan rasin depo a konsène TAGTOA — li se yon lòt pwojè.

---

## 1. Ki sa LOUVIA ye

Platfòm SaaS IA pou biznis ayisyen: **chatbot** ki reponn kesyon, epi **ajan**
ki aji (kòmand, randevou). Fondatè: Roosevelt Forestal.

Sib: **https://louvia.govibeht.com**

Plan konplè: `docs/govibe-ai/ARCHITECTURE.md` · ADR: `docs/govibe-ai/adr/`

## 2. Anviwònman teknik

- Laravel 13, **PHP 8.3** (pa 8.4 — se vèsyon VPS la), `nwidart/laravel-modules`,
  Pest, PHPStan/larastan, Pint.
- ⚠️ **Baz done: MySQL/SQLite, PA Postgres.** VPS la gen `pdo_mysql` + `pdo_sqlite`
  sèlman. **Pa gen pgvector** → Knowledge Base ak embeddings mande yon desizyon
  enfrastrikti anvan Phase 3.
- ⚠️ `composer.lock` dwe rezoud kont **PHP 8.3** (`config.platform.php`). Yon lock
  ki mande 8.4 pase CI men kraze sou deplwaman.

## 3. Modil yo

`Core` · `AIProvider` · `AIRouter` · `AIServices` · `Agents` · `Usage`

Règ: ajoute yon founisè = yon dosye konektè + yon liy nan config. Ajoute yon
modèl ajan = yon klas Template + yon `register()`. Zewo modifikasyon nan kè a.

## 4. Eta (AKTYÈL)

| Kouch | Sa ki genyen |
|---|---|
| Founisè | 8 konektè: OpenAI, Anthropic, Gemini, DeepSeek, Mistral, OpenRouter, HuggingFace, **ElevenLabs** (vwa) |
| Router | skò sou 5 aks, failover, disjonktè, metrik |
| API | `POST /api/v1/chat/completions` (+SSE), `GET /api/v1/models` — konpatib OpenAI |
| Ajan | `AgentDefinition`, `ConfirmationPolicy`, `Conversation` (memwa), `AgentRuntime`, `HaitianCurrency` |
| Modèl | 3 ajan (restoran, klinik, lekòl) + 3 chatbot (sipò, sit entènèt, akèy WhatsApp) |
| Vwa | TTS + Scribe; bibliyotèk vwa; **vwa pa ajan** (`agents.voice_id`); vwa klonaj |
| Paj | `/` akèy · `/agents` · `/agents/nouvo/{modèl}` · `/agents/{id}` · `/agents/{id}/vwa` · `/agents/demo/{modèl}` (chat + apèl) · `/komande` · `/sipo` |
| Tab | `ai_providers`, `ai_models`, `ai_requests`, `agents`, `agent_orders` |
| Kalite | **208 tès Pest**, Pint, PHPStan |

Entèfas: **fon blan, tèks nwa, bouton vèt**, tit an **Anton**, meni sou kote ki
louvri SAN JavaScript (checkbox kache), mobil dabò.

## 5. Frontyè ElevenLabs — opsyon C

Gade `docs/govibe-ai/adr/ADR-002-frontiere-elevenlabs.md`.

**Lakay ElevenLabs**: son an (TTS, Scribe), epi — pou kanal telefòn sèlman —
yon ajan ConvAI ki òganize lakay yo.
**Lakay LOUVIA**: jijman an (konfimasyon, memwa, router, règ goud/dola ayisyen).

Règ koutir: definisyon ajan an lakay nou se sous inik la; yon ajan ConvAI se
yon **pwojeksyon** li, jamè edite bò kote ElevenLabs.

⚠️ Yon ajan ConvAI ki òganize lakay yo pa ka fè `ConfirmationPolicy` respekte
anndan l → yon ajan telefòn rete limite ak **zouti lekti** toutotan zouti l yo
pa branche sou webhook nou yo.

## 6. Kle ak deplwaman

- CI: `.github/workflows/govibe-ai-ci.yml` sou **main**, `workflow_dispatch` +
  parametre `branch`.
- Deplwaman: `.github/workflows/govibe-ai-deploy.yml` (manyèl sèlman,
  `action=diagnose|deploy`). Estrikti sou sèvè a:
  `domains/louvia.govibeht.com/laravel/` (app, `.env`, `vendor/`) +
  `public_html/` (sèlman `index.php` ki boote `../laravel`).
- Kle yo antre pa **secrets GitHub**, deplwaman an ekri yo nan `.env`:
  - `LOUVIA_AI_KEY` + input `ai_provider` → `<FOUNISÈ>_API_KEY`
  - `LOUVIA_ELEVENLABS_KEY` + input `set_voice_key=true` → `ELEVENLABS_API_KEY`
  - `LOUVIA_ELEVENLABS_VOICE_ID` → `ELEVENLABS_VOICE_ID`
- Verifikasyon sou sèvè a (yo pa janm ekri yon kle):
  - `php artisan govibe:providers` — ki founisè ki konfigire
  - `php artisan govibe:voices` — **premye tès reyèl konektè vwa a**

## 7. Sa ki rete

- ⚠️ **Pa gen otantifikasyon**: nenpòt moun ki gen URL la ka kreye yon ajan epi
  wè konesans biznis lòt moun. Phase 1 (kont, òganizasyon, RBAC, izolasyon
  tenan) dwe fèmen sa a anvan yon vrè machann antre done l.
- Egzekisyon zouti: `create_order` se yon non nan yon lis; li pa antre okenn kote.
- Kanal WhatsApp/telefòn: kontra `Channel` egziste, zewo enplemantasyon.
- ConvAI `agents/create`: schema pa verifye (elevenlabs.io bloke nan sandbox la).
- Faktirasyon: `ai_requests` konte jeton, minit vwa yo pa konte.
- Zewo validasyon reyèl: 0 nòt vokal kreyòl kolekte, 0 restoran rele.

## 8. Pyèj konnen

- **PA JANM** mete `fn(...) => [...]` (fonksyon flèch + tablo) andedan `@json(...)`
  oswa lòt direktiv Blade — parser la kase (« Unclosed '[' ») e paj la crash an
  pwodiksyon. Mete lojik la nan `@php … @endphp`, pase yon varyab senp.
- `shouldRenderJsonWhen` dwe kenbe `$request->expectsJson()`: paj yo gen endpwen
  JSON pa yo andeyò `api/*` (vwa demo a, chat sipò a). San sa, yon erè validasyon
  voye yon redireksyon HTML bay yon `fetch()` epi bouton an tonbe an silans.
- Vhost louvia a mare sou **IP piblik** la, pa sou `127.0.0.1`. Yon
  `curl --resolve …:127.0.0.1` tonbe sou vhost catch-all la epi bay « webserver is
  functioning normally » menm lè sit la byen mache. Tès ki fè lwa a se tès
  **piblik** la, epi li dwe egzije siyati `LOUVIA` nan kontni an.
- Sandbox devlopman an: `api.github.com` ak `elevenlabs.io` bloke. Composer:
  `env -u GITHUB_TOKEN -u GH_TOKEN COMPOSER_PROCESS_TIMEOUT=900 composer install --prefer-source`.

## 9. Referans

- Achitekti MVP: https://claude.ai/code/artifact/9d47ec31-f7b9-400a-9a13-a252be3c00b9
- Aperçu vizyèl ekran yo: https://claude.ai/code/artifact/29c69c4b-87c3-4658-8ab0-b2073ab76736
