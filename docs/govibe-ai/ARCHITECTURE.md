# GOVIBE AI — Architecture de la plateforme

> **Statut : PROPOSITION — en attente de validation avant tout code.**
> Écosystème GOVIBE (Haïti → Caraïbe). Fondateur : Roosevelt Forestal.
> Objectif : devenir la principale plateforme IA d'Haïti, puis de la Caraïbe.

---

## 1. Analyse des besoins

### 1.1 Vision produit

GOVIBE AI est une plateforme SaaS qui joue **trois rôles à la fois** :

1. **Passerelle IA unifiée (AI Gateway)** — une seule API, une seule clé, un seul
   système de crédits pour accéder à 10+ fournisseurs IA (OpenAI, Claude, Gemini,
   DeepSeek, Mistral, Qwen, Llama, Gemma, OpenRouter, Hugging Face…), avec routage
   automatique vers le meilleur fournisseur.
2. **Suite d'applications IA** — Chat, Image, Vidéo, Voix, Transcription, OCR,
   Vision, Traduction, Documents, Présentations, Tableurs, Code, Marketing, SEO,
   Branding… livrées progressivement au-dessus de la même passerelle.
3. **Moteur d'Agents IA sectoriels** — chaque entreprise (restaurant, hôtel, école,
   hôpital, église, ONG, cabinet juridique…) crée son propre assistant, entraîné sur
   ses données, exposé sur web/WhatsApp/API.

### 1.2 Contraintes et réalités du marché haïtien

Ces contraintes **façonnent l'architecture** — elles ne sont pas décoratives :

| Réalité | Conséquence architecturale |
|---|---|
| Faible pénétration des cartes bancaires | Paiements MonCash/NatCash/virements + **crédits prépayés** comme modèle central (pas seulement l'abonnement récurrent) |
| Connectivité intermittente et chère | Réponses en streaming, payloads légers, retry idempotent côté API, front-ends sobres |
| Créole haïtien mal servi par les modèles | Couche de **prompt engineering créole** + scoring qualité par langue dans le routeur + fine-tuning/glossaires plus tard |
| Coût = facteur n°1 | Le routeur optimise le coût par défaut ; tarification en HTG/USD ; plans très granulaires |
| Marché PME/ONG/administration | Templates métier prêts à l'emploi (factures, devis, projets ONG, suivi-évaluation, documents administratifs) |
| 4 langues (fr, ht, en, es) | i18n natif partout : UI, e-mails, documentation API, sorties des modèles |

### 1.3 Acteurs

- **Utilisateur final** (B2C) : chat, images, documents… via dashboard.
- **Entreprise / organisation** (B2B) : équipe, agents IA, API, facturation.
- **Développeur** : API RESTful + clés API + documentation + SDK.
- **Administrateur GOVIBE** : fournisseurs, tarifs, plans, modération, analytics.
- **Système** : jobs, webhooks, facturation automatique, monitoring.

### 1.4 Exigences non fonctionnelles

- **Échelle** : conçu pour des millions de requêtes API/mois (voir §4.6).
- **Disponibilité** : dégradation gracieuse — si un fournisseur tombe, le routeur bascule.
- **Sécurité** : clés API hashées, secrets chiffrés, rate limiting, audit complet.
- **Extensibilité** : ajouter un fournisseur IA = 1 connecteur + 1 entrée de config,
  **zéro modification du cœur** (Open/Closed Principle).
- **Testabilité** : logique métier pure isolée du framework, connecteurs mockables.

---

## 2. Modules identifiés

Découpage en **13 modules** à frontières nettes (chacun pourrait devenir un service
indépendant plus tard — voir §3.1) :

| # | Module | Responsabilité | Dépend de |
|---|---|---|---|
| 1 | **Core** | Users, organisations/équipes, rôles & permissions, i18n, settings | — |
| 2 | **AIProvider** | Connecteurs fournisseurs, registre, DTOs normalisés, health checks | Core |
| 3 | **AIRouter** | Sélection auto du fournisseur (coût/vitesse/qualité/préférences/dispo), failover, circuit breaker | AIProvider |
| 4 | **AIServices** | Endpoints produits : chat, image, video, speech, transcription, ocr, vision, translation, documents, presentations, spreadsheet, coding, marketing, seo, branding | AIRouter, Credits |
| 5 | **Agents** | Moteur d'agents : personas sectoriels, base de connaissances (RAG), canaux (web widget, WhatsApp, API) | AIServices |
| 6 | **Billing** | Plans, abonnements, **portefeuille de crédits**, factures, paiements (MonCash, Stripe, virement manuel avec preuve) | Core |
| 7 | **ApiPlatform** | Clés API, scopes, rate limits, quotas, versioning, webhooks sortants | Core, Billing |
| 8 | **Usage** | Journal d'utilisation (chaque requête IA), agrégats, exports | — (écoute les events) |
| 9 | **Analytics** | Tableaux de bord : dépenses, latences, top modèles, tendances | Usage |
| 10 | **AdminPanel** | Back-office GOVIBE : fournisseurs, tarifs, plans, users, modération, santé système | tous |
| 11 | **UserPanel** | Dashboard client : playground, agents, crédits, clés, factures, usage | tous |
| 12 | **HaitiPack** | Services localisés : créole (texte+voix), factures/devis, caisse, documents administratifs, projets ONG, budgets, suivi-évaluation, éducation | AIServices |
| 13 | **Marketplace** | Agents/templates/prompts publiés par la communauté, revenue share | Agents, Billing |

**Règle de dépendance** : les flèches vont toujours vers le bas (AIServices → AIRouter
→ AIProvider). Jamais l'inverse. La communication remontante passe par des **Events**.

---

## 3. Architecture complète

### 3.1 Style : monolithe modulaire → extraction progressive

**Décision : monolithe modulaire Laravel, PAS des micro-services au départ.**

Justification (décision de CTO, pas de mode) :
- Une petite équipe livre 5× plus vite dans un monolithe bien découpé.
- Les micro-services ajoutent un coût opérationnel (réseau, observabilité,
  déploiements multiples) injustifié avant ~10⁶ req/jour soutenus.
- Les frontières de modules ci-dessus sont dessinées pour qu'une extraction future
  soit mécanique : le **candidat n°1 à l'extraction est le chemin chaud
  `ApiPlatform + AIRouter + AIProvider`** (le « Gateway »), stateless, scalable
  horizontalement, extractible en service dédié quand le trafic l'exige.

```
                        ┌────────────────────────────────────────────┐
                        │              GOVIBE AI (Laravel)           │
  Web (Blade/Inertia) ──►  UserPanel   AdminPanel                    │
                        │      │           │                         │
  API v1 (REST) ────────►  ApiPlatform ────┼──► AIServices ──► Agents│
                        │      │           │        │                │
                        │   Billing ◄── events ── AIRouter           │
                        │      │                    │                │
                        │   Usage/Analytics     AIProvider ──────────┼──► OpenAI, Claude,
                        │                                            │    Gemini, DeepSeek,
                        └───────┬──────────┬──────────┬──────────────┘    Mistral, Qwen,
                                │          │          │                   Llama, Gemma,
                            PostgreSQL   Redis     S3-compatible          OpenRouter, HF…
                            (+ pgvector) (cache,   (fichiers,
                                         queues,    images, audio)
                                         rate limit)
```

### 3.2 Stack technique

| Couche | Choix | Pourquoi |
|---|---|---|
| Framework | **Laravel 12** (dernière stable) + PHP 8.4 | Exigence projet ; écosystème mûr |
| Serveur app | **Laravel Octane (FrankenPHP)** | Chemin API chaud : app bootée en mémoire, latence divisée |
| BDD | **PostgreSQL 16 + pgvector** | JSONB pour payloads IA, pgvector pour le RAG des agents (pas de service vectoriel séparé au départ) |
| Cache / queues / rate-limit | **Redis** (+ **Horizon**) | Un seul backend pour cache, files, throttling |
| Fichiers | S3-compatible (MinIO en dev, Wasabi/S3 en prod) | Images, audio, documents générés |
| Temps réel | SSE pour le streaming des tokens ; **Laravel Reverb** (websockets) pour les dashboards | SSE = simple, passe les proxys |
| Recherche RAG | pgvector + embeddings via la couche AIProvider | Cohérent : les embeddings passent aussi par le routeur |
| Observabilité | OpenTelemetry + Prometheus/Grafana, Sentry | Indispensable pour opérer un gateway |
| Docs API | OpenAPI 3.1 générée depuis le code (Scribe/Scramble) + portail docs | « Comparable aux grands fournisseurs » |
| Docker | docker-compose (dev) → images séparées web/worker/scheduler (prod) | Exigence projet |
| CI/CD | GitHub Actions : lint (Pint), analyse statique (PHPStan lvl 8), tests (Pest), build image, deploy | Exigence projet |

### 3.3 La couche AI Provider (le cœur extensible)

Principe : **contrats + connecteurs + manifestes**. Le cœur ne connaît que des
interfaces et des DTOs normalisés ; chaque fournisseur est un plugin.

```php
// Contrats (Modules/AIProvider/Contracts)
interface AIProvider {
    public function key(): string;                    // 'openai', 'anthropic', …
    public function capabilities(): array;            // [Capability::Chat, …]
    public function models(): Collection;             // depuis le manifeste
    public function health(): ProviderHealth;         // sonde + cache Redis
}
interface SupportsChat        { public function chat(ChatRequest $r): ChatResponse; public function streamChat(ChatRequest $r): Generator; }
interface SupportsEmbeddings  { public function embed(EmbedRequest $r): EmbedResponse; }
interface SupportsImages      { public function generateImage(ImageRequest $r): ImageResponse; }
interface SupportsSpeech      { public function textToSpeech(SpeechRequest $r): SpeechResponse; }
interface SupportsTranscription { public function transcribe(TranscriptionRequest $r): TranscriptionResponse; }
// … SupportsVision, SupportsVideo, SupportsOcr (composables : un provider implémente ce qu'il sait faire)
```

- **DTOs normalisés** (`ChatRequest`, `ChatResponse`, `Usage{input_tokens, output_tokens, cost_microusd}`…) :
  l'application parle UN dialecte ; chaque connecteur traduit vers/depuis l'API du fournisseur.
- **Un connecteur = un dossier** : `Connectors/OpenAI/`, `Connectors/Anthropic/`,
  `Connectors/Gemini/`, `Connectors/DeepSeek/`, `Connectors/Mistral/`,
  `Connectors/Qwen/`, `Connectors/OpenRouter/`, `Connectors/HuggingFace/`
  (Llama et Gemma sont des **modèles** servis via OpenRouter/HF/Groq — pas des connecteurs propres).
- **Manifeste par fournisseur** (`manifest.php`) : modèles, capacités, prix
  (micro-USD/token), context window, langues fortes, endpoints. Les prix sont
  **surchargés en BDD** (`ai_models`) pour être modifiables depuis l'admin sans déploiement.
- **ProviderRegistry** : découverte par tag de service container. **Ajouter un
  fournisseur = créer le dossier du connecteur + son manifeste. Aucune autre ligne ne change.**
- Beaucoup de fournisseurs (DeepSeek, Qwen, OpenRouter, Groq…) sont compatibles
  API-OpenAI → un connecteur générique `OpenAICompatible` configurable réduit le travail à ~20 lignes.

### 3.4 L'AI Router

Entrée : `RoutingContext { capability, messages/payload, user, organisation, plan,
préférences, contrainte (cost|speed|quality|balanced), langue détectée }`.

Pipeline :

1. **Filtrage** — providers ayant la capacité demandée, activés pour le plan de
   l'utilisateur, **sains** (circuit breaker fermé), dans les limites de quota.
2. **Scoring** — score pondéré par candidat :
   `score = w_cost·f(prix) + w_speed·f(latence p95 observée) + w_quality·f(note qualité par tâche ET par langue) + w_pref·f(préférences user/org) + w_avail·f(taux d'erreur récent)`
   - Latences/erreurs = **mesures réelles glissantes** stockées dans Redis (pas des constantes).
   - La note qualité par langue permet de router le créole vers les modèles qui le gèrent le mieux.
   - Les poids sont configurables par plan, par organisation, et par requête (`"routing": "cost"` dans l'API).
3. **Exécution avec failover** — essai du meilleur candidat ; en cas d'erreur 5xx/timeout :
   circuit breaker (Redis, seuil d'échecs → ouverture → half-open), passage au candidat
   suivant, jusqu'à épuisement. Retries idempotents via `Idempotency-Key`.
4. **Post-traitement** — normalisation de la réponse, calcul du coût réel,
   émission de `AIRequestCompleted` (→ Usage, Billing, Analytics, en asynchrone).

Le client peut aussi **forcer** un modèle précis (`"model": "anthropic/claude-…"`),
comme sur OpenRouter — le routeur devient alors un simple exécuteur avec failover.

### 3.5 Crédits, abonnements, facturation

- **Le crédit GOVIBE est l'unité de compte** (1 crédit = montant en micro-USD, taux
  HTG affiché). Chaque requête IA débite le portefeuille au coût réel × marge du plan.
- **Ledger en double entrée** (pattern déjà éprouvé dans TAGTOA — Event Wallet) :
  `credit_transactions` immuables, solde = somme, jamais d'UPDATE de solde sans
  transaction + `lockForUpdate`. Débit **réservé** avant l'appel provider, **ajusté**
  au coût réel après (évite les portefeuilles négatifs sur les réponses longues).
- **Plans** : Free (petit quota mensuel), Pro, Business, Enterprise + **recharges
  prépayées** (modèle dominant en Haïti). Abonnement = crédits mensuels + limites
  (req/min, modèles accessibles, nb d'agents, sièges).
- **Paiements** : MonCash (API), carte via Stripe, virement/preuve manuelle
  (pattern TAGTOA PAY réutilisé). Factures PDF multilingues, numérotation légale.

### 3.6 Moteur d'Agents IA

- **Agent = persona + instructions + base de connaissances + outils + canaux.**
- **Templates sectoriels** (restaurants, hôtels, écoles, universités, hôpitaux,
  cliniques, églises, ONG, juridique, comptabilité, immobilier, commerce,
  agriculture, tourisme, administration publique) : prompts système pré-écrits en
  4 langues + champs à remplir (menu, horaires, tarifs…).
- **RAG** : upload de documents (PDF, DOCX, site web) → chunking → embeddings
  (via AIRouter) → pgvector → retrieval à chaque question.
- **Canaux** : widget web embarquable, API, WhatsApp (Twilio — pattern TAGTOA
  réutilisé), plus tard SMS/Telegram.
- **Outils d'agent** (function calling) : progressivement — prise de rendez-vous,
  création de devis/factures (HaitiPack), remontée vers un humain.

### 3.7 API publique (extraits)

```
POST   /api/v1/chat/completions        # compatible OpenAI (adoption immédiate des SDK existants)
POST   /api/v1/images/generations
POST   /api/v1/audio/speech            # TTS
POST   /api/v1/audio/transcriptions
POST   /api/v1/ocr
POST   /api/v1/translations
POST   /api/v1/embeddings
GET    /api/v1/models                  # catalogue agrégé multi-fournisseurs
POST   /api/v1/agents/{id}/chat
GET    /api/v1/usage                   # journal + agrégats
GET    /api/v1/credits/balance
```

Décision forte : **le endpoint chat est compatible avec le format OpenAI** — tout
SDK existant (Python, JS…) fonctionne avec GOVIBE AI en changeant `base_url` et la
clé. C'est la stratégie d'adoption d'OpenRouter/DeepSeek/Mistral, et la bonne.

Auth : `Authorization: Bearer gvb_live_…` (clés hashées SHA-256 en BDD, préfixe
visible, scopes, expiration, IP allowlist). Versioning par préfixe d'URL (`/v1`).
Erreurs normalisées `{ "error": { "type", "code", "message", "doc_url" } }` en 4 langues.

### 3.8 Queues, cache, events (obligatoires, pas optionnels)

- **Synchrones** : uniquement l'appel IA lui-même (streaming) et les lectures dashboard.
- **Jobs (Horizon, files nommées)** : `high` (webhooks, notifications), `default`
  (journalisation usage, agrégats analytics, factures PDF), `heavy` (ingestion RAG,
  génération vidéo/audio longue — avec timeout et retry backoff), `low` (exports, e-mails).
- **Events de domaine** : `AIRequestCompleted`, `CreditsDebited`, `CreditsLow`,
  `SubscriptionRenewed`, `PaymentReceived`, `ProviderUnhealthy`, `AgentTrained` —
  les modules s'écoutent par events, jamais par appels directs remontants.
- **Cache** : catalogue modèles (5 min), santé providers (30 s), solde crédité
  (write-through), config plans (1 h), réponses de traduction identiques (LRU opt-in).

---

## 4. Base de données (tables principales)

Conventions : `id` BIGINT auto, `uuid` public exposé dans l'API, timestamps,
soft-deletes où pertinent, montants monétaires en **unités mineures** (micro-USD
pour les coûts IA, centimes pour la facturation), FK indexées.

### 4.1 Core & organisations
```
users                (id, uuid, name, email, password, locale, timezone, avatar, 2fa_secret, status)
organizations        (id, uuid, name, slug, owner_id→users, sector, locale, currency, settings JSONB)
organization_user    (organization_id, user_id, role[owner|admin|developer|member], invited_at)
```
*(rôles fins via spatie/laravel-permission)*

### 4.2 Fournisseurs & modèles IA
```
ai_providers         (id, key UNIQUE, name, status[active|degraded|disabled], base_url_override,
                      priority, config JSONB, credentials_encrypted)
ai_models            (id, provider_id→ai_providers, key, name, capabilities JSONB,
                      context_window, input_price_micro, output_price_micro,   -- prix de revient
                      sell_input_price_micro, sell_output_price_micro,          -- prix de vente
                      quality_scores JSONB {task:{lang:score}}, is_active, is_free_tier)
provider_health_snapshots (id, provider_id, window_start, p50_ms, p95_ms, error_rate, sample_count)
```

### 4.3 API & usage
```
api_keys             (id, uuid, organization_id, name, key_prefix, key_hash UNIQUE, scopes JSONB,
                      rate_limit_rpm, monthly_quota_credits, allowed_ips JSONB, last_used_at, expires_at, revoked_at)
ai_requests          (id, uuid, organization_id, user_id?, api_key_id?, capability, model_id,
                      provider_id, routed_reason, status[ok|failed|failover], latency_ms,
                      input_tokens, output_tokens, cost_micro, billed_credits,
                      idempotency_key, error_code?, meta JSONB, created_at)   -- partitionnée par mois
usage_daily          (id, organization_id, date, capability, model_id, requests, tokens_in,
                      tokens_out, cost_micro, billed_credits)                 -- agrégat pour dashboards
webhook_endpoints    (id, organization_id, url, secret, events JSONB, is_active)
webhook_deliveries   (id, endpoint_id, event, payload JSONB, status, attempts, next_retry_at)
```

### 4.4 Billing (ledger crédits + abonnements)
```
plans                (id, key, name, monthly_price_cents, currency, monthly_credits,
                      limits JSONB {rpm, agents, seats, models}, is_public)
subscriptions        (id, organization_id, plan_id, status[trial|active|past_due|cancelled],
                      current_period_start/end, cancel_at?)
credit_wallets       (id, organization_id UNIQUE, balance_micro, low_threshold_micro)
credit_transactions  (id, uuid, wallet_id, type[purchase|subscription_grant|debit|hold|release|refund|adjustment],
                      amount_micro signed, balance_after_micro, ai_request_id?, payment_id?,
                      idempotency_key UNIQUE, meta JSONB, created_at)          -- IMMUABLE, append-only
payments             (id, uuid, organization_id, method[moncash|stripe|manual|…], amount_cents,
                      currency, status[pending|proof_submitted|confirmed|failed|refunded],
                      external_ref, proof_path?, confirmed_by?, meta JSONB)
invoices             (id, uuid, organization_id, number UNIQUE, period, lines JSONB,
                      subtotal_cents, tax_cents, total_cents, currency, status, pdf_path)
```

### 4.5 Chat, agents, RAG, marketplace
```
conversations        (id, uuid, organization_id, user_id?, agent_id?, title, model_pref?, meta JSONB)
messages             (id, conversation_id, role[system|user|assistant|tool], content JSONB,
                      ai_request_id?, created_at)
agents               (id, uuid, organization_id, name, slug, sector_template, system_prompt,
                      model_policy JSONB, tools JSONB, channels JSONB, languages JSONB,
                      status, is_public_marketplace)
agent_documents      (id, agent_id, filename, storage_path, mime, status[pending|indexed|failed], meta)
agent_chunks         (id, document_id, agent_id, content TEXT, embedding vector(1536), meta JSONB)
                     -- index HNSW pgvector
marketplace_items    (id, uuid, type[agent_template|prompt|workflow], author_org_id, title,
                      description, price_credits, sector, languages JSONB, installs, rating, status)
audit_logs           (id, organization_id?, user_id?, action, subject_type, subject_id, meta JSONB, ip, created_at)
files                (id, uuid, organization_id, kind[upload|generated], storage_path, mime, size, expires_at?)
```

Points d'échelle : `ai_requests` **partitionnée par mois** ; les dashboards lisent
`usage_daily` (agrégé par job), jamais la table brute ; rate limiting dans Redis,
pas en SQL.

---

## 5. Arborescence du projet

Nouveau dépôt applicatif (ou dossier racine `govibe-ai/` dans ce repo — à trancher),
Laravel 12 + `nwidart/laravel-modules` :

```
govibe-ai/
├── app/                          # noyau minimal (kernels, providers globaux, middleware SetLocale)
├── Modules/
│   ├── Core/
│   │   ├── app/{Models, Http, Services, Policies}
│   │   ├── database/migrations/
│   │   ├── lang/{fr,ht,en,es}/
│   │   └── routes/{web,api}.php
│   ├── AIProvider/
│   │   ├── app/
│   │   │   ├── Contracts/        # AIProvider, SupportsChat, SupportsImages, …
│   │   │   ├── DTO/              # ChatRequest, ChatResponse, Usage, …
│   │   │   ├── Connectors/
│   │   │   │   ├── OpenAI/          {OpenAIProvider.php, manifest.php}
│   │   │   │   ├── Anthropic/
│   │   │   │   ├── Gemini/
│   │   │   │   ├── DeepSeek/
│   │   │   │   ├── Mistral/
│   │   │   │   ├── OpenRouter/
│   │   │   │   ├── HuggingFace/
│   │   │   │   └── OpenAICompatible/  # connecteur générique configurable
│   │   │   ├── Registry/ProviderRegistry.php
│   │   │   └── Health/{HealthProbe, CircuitBreaker}
│   │   └── tests/                # tests unitaires purs + contract tests par connecteur
│   ├── AIRouter/
│   │   └── app/{Routing/{Router, CandidateFilter, Scorer, FailoverExecutor},
│   │          Policies/RoutingPolicy, Support/LanguageDetector}
│   ├── AIServices/
│   │   └── app/{Chat, Image, Speech, Transcription, Ocr, Vision, Translation,
│   │          Documents, Presentations, Spreadsheet, Coding, Marketing, Seo, Branding}/
│   │          # chaque service : Controller + Service + Requests + Resources
│   ├── Agents/
│   │   └── app/{Models, Services/{AgentRuntime, Rag/{Ingestor, Chunker, Retriever}},
│   │          Channels/{WebWidget, WhatsApp, Api}, Templates/sectors/*.php}
│   ├── Billing/
│   │   └── app/{Models, Services/{CreditLedger, SubscriptionService, InvoiceService},
│   │          Payments/{MonCash, Stripe, ManualProof}, Jobs, Events}
│   ├── ApiPlatform/
│   │   └── app/{Models/ApiKey, Http/Middleware/{AuthenticateApiKey, EnforceRateLimit,
│   │          EnforceQuota, IdempotencyMiddleware}, Services, OpenApi/}
│   ├── Usage/          # listeners de AIRequestCompleted, agrégats, exports
│   ├── Analytics/
│   ├── AdminPanel/     # Blade/Livewire — fournisseurs, tarifs, plans, users, santé
│   ├── UserPanel/      # dashboard client + playground + docs portal
│   ├── HaitiPack/      # factures/devis, caisse, docs administratifs, ONG, éducation
│   └── Marketplace/
├── config/govibe.php             # poids du routeur, marges, langues, devises
├── database/                     # migrations transverses uniquement
├── docker/
│   ├── Dockerfile                # multi-stage (base → web Octane / worker / scheduler)
│   ├── docker-compose.yml        # dev : app, postgres+pgvector, redis, minio, mailpit
│   └── docker-compose.prod.yml
├── docs/
│   ├── ARCHITECTURE.md           # ce document
│   ├── api/                      # OpenAPI + guides (4 langues)
│   └── adr/                      # Architecture Decision Records
├── tests/                        # Pest : Unit (pur), Feature (HTTP), Contract (connecteurs)
├── .github/workflows/{ci.yml, deploy.yml}
└── composer.json
```

Principes de code appliqués partout :
- **Controllers minces** → Form Requests (validation) → **Services** (métier) →
  Models/Repositories. Repository Pattern **seulement** où il paie (ledger crédits,
  requêtes usage, registre providers) — pas de repositories anémiques sur tout.
- Logique pure (scoring du routeur, calculs de coût, chunking RAG, ledger) dans des
  classes **sans dépendance Laravel** → testables en PHPUnit/Pest pur (leçon TAGTOA).
- SOLID : connecteurs = Open/Closed ; interfaces capacités = Interface Segregation ;
  le routeur dépend d'abstractions = Dependency Inversion.

---

## 6. Plan de développement par phases

Chaque phase = livrable utilisable + tests + docs + CI verte. PR courtes, draft → CI → squash.

### Phase 0 — Fondations (1 semaine)
Squelette Laravel 12 + modules, Docker compose complet, CI (Pint + PHPStan + Pest),
Postgres+pgvector, Redis, Horizon, auth de base, i18n 4 langues (middleware SetLocale
— pattern TAGTOA), ADR-001 (monolithe modulaire), conventions.

### Phase 1 — Couche AIProvider + AIRouter + Chat (2-3 semaines) ⭐ cœur de la valeur
Contrats + DTOs, connecteurs **OpenAI, Anthropic, Gemini, DeepSeek, OpenRouter**
(+ générique OpenAICompatible ⇒ Mistral/Qwen/HF rapides ensuite), manifestes + tables
`ai_providers`/`ai_models`, routeur v1 (filtrage + scoring + failover + circuit breaker),
`POST /v1/chat/completions` compatible OpenAI avec streaming SSE, journal `ai_requests`.
**Fin de phase 1 : GOVIBE AI fonctionne déjà comme passerelle IA.**

### Phase 2 — Monétisation (2 semaines)
Ledger crédits (hold → debit → adjust), plans + abonnements + gating, paiements
MonCash + Stripe + preuve manuelle, factures PDF, clés API (hash, scopes), rate
limiting + quotas Redis, middleware d'idempotence, `GET /v1/usage`, `GET /v1/credits/balance`.

### Phase 3 — Dashboards + Docs API (2 semaines)
UserPanel (playground chat, crédits, clés, usage, factures, préférences de routage),
AdminPanel (providers, prix, plans, users, santé, marges), portail de documentation
API multilingue généré depuis OpenAPI + guides de démarrage (curl, JS, Python, PHP).

### Phase 4 — Services IA v1 (3 semaines)
Images, Translation (avec soin créole), Speech (TTS), Transcription, OCR, Vision,
Embeddings publics. Chaque service : endpoint REST + page playground + docs + tests.

### Phase 5 — Agents IA (3-4 semaines)
Moteur d'agents + RAG pgvector, 15 templates sectoriels en 4 langues, widget web
embarquable, canal WhatsApp (Twilio), analytics par agent.

### Phase 6 — HaitiPack (2-3 semaines)
Factures/devis IA, documents administratifs, projets ONG + budgets + suivi-évaluation,
outils éducation, voix créole (TTS affiné), gestion de caisse assistée.

### Phase 7 — Suite productivité + Marketplace (continu)
Documents, Presentations, Spreadsheet, Coding, Marketing, SEO, Branding ; marketplace
d'agents/templates avec revenue share ; SDKs officiels (JS, PHP, Python).

### Phase 8 — Échelle & durcissement (continu, commence dès la phase 2)
Partitionnement `ai_requests`, read replicas, autoscaling workers, tests de charge
(k6), chaos providers, SLO + alerting, éventuelle extraction du Gateway en service.

**Priorité absolue : Phases 1-2.** Une passerelle multi-fournisseurs monétisée par
crédits est un produit vendable à elle seule ; tout le reste s'empile dessus.

---

## 7. Décisions à valider avant de coder

1. **Emplacement du code** : nouveau dépôt `govibe-ai` (recommandé — cycle de vie,
   CI et déploiement indépendants de TAGTOA) ou dossier dans ce repo ?
2. **Endpoint compatible OpenAI** : confirmé ? (recommandé fortement, §3.7)
3. **Monolithe modulaire d'abord** : confirmé ? (§3.1)
4. **PostgreSQL + pgvector** (et non MySQL) : confirmé ? (§3.2)
5. **Stack front des dashboards** : Blade + Livewire (simple, cohérent avec l'existant)
   ou Inertia + Vue/React ? Recommandation : **Livewire** pour livrer vite.
6. **Ordre des phases** : OK, ou prioriser Agents avant la monétisation ?
7. **Hébergement cible** : VPS actuel ou infra dédiée (le gateway mérite sa propre machine) ?

---

*Document vivant — sera mis à jour à chaque décision (ADR) et à chaque fin de phase.*
