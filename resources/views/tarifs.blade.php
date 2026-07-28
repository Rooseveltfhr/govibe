@extends('layouts.public')

@section('title', 'Tarifs & Packages — GOVIBE Innovation Hub')
@section('description', 'Découvrez nos offres ERP adaptées aux PME, ONG, centres de formation et grandes entreprises en Haïti.')

@section('head')
<style>
/* ── HERO ───────────────────────────────────────────────── */
.tarifs-hero {
  background:linear-gradient(135deg,#080010 0%,#0a0a0a 60%,#0d0005 100%);
  padding:90px 1.5rem 70px;
  text-align:center;
  position:relative;
  overflow:hidden;
}
.tarifs-hero::before {
  content:'';
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(124,58,237,.06) 1px,transparent 1px),
    linear-gradient(90deg,rgba(124,58,237,.06) 1px,transparent 1px);
  background-size:52px 52px;
  pointer-events:none;
}
.tarifs-hero-inner { position:relative;z-index:2; }
.tarifs-hero h1 {
  font-size:clamp(2.2rem,6vw,3.8rem);
  color:#fff;
  margin:.7rem 0;
  line-height:1.05;
}
.tarifs-hero h1 span { color:var(--gv-primary); }
.tarifs-hero p {
  color:rgba(255,255,255,.6);
  max-width:580px;
  margin:.8rem auto 0;
  font-size:1rem;
  line-height:1.75;
}

/* ── BILLING TOGGLE ────────────────────────────────────── */
.billing-wrap {
  display:flex;
  align-items:center;
  justify-content:center;
  gap:1rem;
  margin-top:2rem;
}
.billing-wrap span { color:rgba(255,255,255,.7); font-size:.9rem; font-weight:600; }
.billing-toggle {
  width:52px;height:28px;background:#374151;
  border-radius:50px;position:relative;cursor:pointer;
  transition:background .3s;border:none;
}
.billing-toggle.yearly { background:var(--gv-primary); }
.billing-toggle::after {
  content:'';
  position:absolute;top:3px;left:3px;
  width:22px;height:22px;background:#fff;
  border-radius:50%;transition:transform .3s;
}
.billing-toggle.yearly::after { transform:translateX(24px); }
.save-badge {
  background:rgba(16,185,129,.15);
  border:1px solid rgba(16,185,129,.3);
  color:#10B981;padding:.25rem .7rem;
  border-radius:50px;font-size:.72rem;font-weight:700;
  letter-spacing:.08em;
}

/* ── PLANS GRID ─────────────────────────────────────────── */
.plans-section {
  background:#f8fafc;
  padding:70px 1.5rem;
}
.plans-grid {
  max-width:1200px;
  margin:0 auto;
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:1.5rem;
  align-items:start;
}
.plan-card {
  background:#fff;
  border:2px solid #e5e7eb;
  border-radius:20px;
  padding:2rem 1.5rem;
  transition:transform .3s,box-shadow .3s,border-color .3s;
  position:relative;
  overflow:hidden;
}
.plan-card:hover {
  transform:translateY(-6px);
  box-shadow:0 24px 60px rgba(0,0,0,.12);
}
.plan-card.featured {
  border-color:var(--gv-primary);
  transform:scale(1.03);
  box-shadow:0 24px 60px rgba(220,38,38,.18);
  z-index:1;
}
.plan-card.featured:hover { transform:scale(1.03) translateY(-6px); }
.plan-badge {
  position:absolute;top:0;right:0;
  padding:.35rem .9rem;
  font-size:.7rem;font-weight:800;
  letter-spacing:.12em;text-transform:uppercase;
  border-bottom-left-radius:12px;
  color:#fff;
}
.plan-icon {
  width:52px;height:52px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;margin-bottom:1.1rem;
}
.plan-name {
  font-family:'Anton',sans-serif;
  font-size:1.4rem;color:#0f172a;
  margin-bottom:.3rem;
}
.plan-target {
  font-size:.78rem;color:#64748b;
  margin-bottom:.9rem;
  display:flex;align-items:center;gap:.35rem;
}
.plan-desc { font-size:.88rem;color:#475569;margin-bottom:1.4rem;line-height:1.6; }
.plan-price {
  display:flex;align-items:flex-end;gap:.3rem;
  margin-bottom:1.5rem;
}
.plan-price .amount {
  font-family:'Anton',sans-serif;
  font-size:2.4rem;line-height:1;color:#0f172a;
}
.plan-price .currency {
  font-size:.8rem;font-weight:700;color:#64748b;
  margin-bottom:.4rem;
}
.plan-price .period {
  font-size:.78rem;color:#94a3b8;
  margin-bottom:.35rem;
}
.plan-features {
  list-style:none;padding:0;margin:0 0 1.8rem;
  display:flex;flex-direction:column;gap:.55rem;
}
.plan-features li {
  font-size:.85rem;color:#374151;
  display:flex;align-items:flex-start;gap:.5rem;
  line-height:1.4;
}
.plan-features li i { color:#10B981;margin-top:.15rem;flex-shrink:0; }
.plan-btn {
  display:block;text-align:center;
  padding:.8rem 1.5rem;border-radius:50px;
  font-weight:700;font-size:.9rem;
  transition:all .3s;cursor:pointer;
  text-decoration:none;
  border:2px solid transparent;
}
.plan-btn-primary {
  background:linear-gradient(135deg,var(--gv-primary),#991b1b);
  color:#fff;
}
.plan-btn-primary:hover { transform:translateY(-2px);box-shadow:0 10px 24px rgba(220,38,38,.35);color:#fff; }
.plan-btn-outline {
  background:transparent;
  border-color:#e5e7eb;
  color:#374151;
}
.plan-btn-outline:hover { border-color:#6b7280;background:#f1f5f9;color:#0f172a; }

/* Enterprise card override */
.plan-card.enterprise { background:linear-gradient(135deg,#0f172a,#1e293b); border-color:#334155; }
.plan-card.enterprise .plan-name,
.plan-card.enterprise .plan-price .amount { color:#fff; }
.plan-card.enterprise .plan-desc,
.plan-card.enterprise .plan-price .period { color:#94a3b8; }
.plan-card.enterprise .plan-features li { color:#cbd5e1; }
.plan-card.enterprise .plan-btn-outline { border-color:#475569;color:#e2e8f0; }
.plan-card.enterprise .plan-btn-outline:hover { border-color:#7c3aed;background:rgba(124,58,237,.15);color:#fff; }

/* ── FAQ ─────────────────────────────────────────────────── */
.faq-section { padding:70px 1.5rem;background:#fff; }
.faq-wrap { max-width:820px;margin:0 auto; }
.faq-item {
  border:1px solid #e5e7eb;
  border-radius:14px;
  margin-bottom:1rem;
  overflow:hidden;
}
.faq-q {
  display:flex;align-items:center;justify-content:space-between;
  padding:1.1rem 1.4rem;cursor:pointer;
  font-weight:600;color:#0f172a;font-size:.95rem;
  gap:1rem;
  background:#fff;
  border:none;width:100%;text-align:left;
  transition:background .2s;
}
.faq-q:hover { background:#f8fafc; }
.faq-q i { color:var(--gv-primary);flex-shrink:0;transition:transform .3s; }
.faq-q.open i { transform:rotate(45deg); }
.faq-a {
  max-height:0;overflow:hidden;
  transition:max-height .4s ease;
  padding:0 1.4rem;color:#475569;font-size:.9rem;line-height:1.75;
}
.faq-a.open { max-height:300px;padding:0 1.4rem 1.2rem; }

/* ── CTA BOTTOM ──────────────────────────────────────────── */
.cta-bottom {
  background:linear-gradient(135deg,#0a0000,#1a000a);
  padding:70px 1.5rem;text-align:center;
}
.cta-bottom h2 { color:#fff;font-size:clamp(1.8rem,4vw,2.8rem);margin-bottom:.8rem; }
.cta-bottom p { color:rgba(255,255,255,.6);font-size:.97rem;max-width:500px;margin:0 auto 2rem; }
</style>
@endsection

@section('content')

{{-- ── HERO ── --}}
<section class="tarifs-hero">
  <div class="tarifs-hero-inner">
    <span class="gv-stag"><i class="fas fa-tags"></i> Tarifs & Packages</span>
    <h1>La solution GOVIBE ERP<br><span>au bon prix</span></h1>
    <p>Des offres flexibles pour PME, ONG, centres de formation et grandes entreprises haïtiennes.</p>

    <div class="billing-wrap" x-data="{ yearly: false }">
      <span>Mensuel</span>
      <button class="billing-toggle" :class="{ yearly }" @click="yearly = !yearly" aria-label="Basculer facturation annuelle"></button>
      <span>Annuel <span class="save-badge">-17%</span></span>
    </div>

    <div style="margin-top:2.5rem;display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;">
      <a href="{{ route('home') }}#reservation" class="gv-btn-prim">
        <i class="fas fa-calendar-alt"></i> Démarrer maintenant
      </a>
      <a href="https://wa.me/50948174124" target="_blank" rel="noopener" class="gv-btn-outline" style="color:#fff;border-color:rgba(255,255,255,.3);">
        <i class="fab fa-whatsapp"></i> Parler à un conseiller
      </a>
    </div>
  </div>
</section>

{{-- ── PLANS GRID ── --}}
<section class="plans-section" x-data="{ yearly: false }">

  <div style="text-align:center;margin-bottom:2.5rem;">
    <div class="billing-wrap" style="margin-top:0;">
      <span style="color:#374151">Mensuel</span>
      <button class="billing-toggle" :class="{ yearly }" @click="yearly = !yearly" aria-label="Facturation"></button>
      <span style="color:#374151">Annuel <span class="save-badge">-17%</span></span>
    </div>
  </div>

  <div class="plans-grid">
    @foreach($plans as $plan)
    @php
      $slug     = is_object($plan) ? $plan->slug : ($plan['slug'] ?? '');
      $name     = is_object($plan) ? $plan->name : ($plan['name'] ?? '');
      $desc     = is_object($plan) ? $plan->description : ($plan['description'] ?? '');
      $target   = is_object($plan) ? $plan->target_audience : ($plan['target_audience'] ?? '');
      $monthly  = is_object($plan) ? $plan->price_monthly : ($plan['price_monthly'] ?? 0);
      $yearly   = is_object($plan) ? $plan->price_yearly : ($plan['price_yearly'] ?? 0);
      $currency = is_object($plan) ? $plan->currency : ($plan['currency'] ?? 'HTG');
      $features = is_object($plan) ? $plan->features : ($plan['features'] ?? []);
      $color    = is_object($plan) ? $plan->color : ($plan['color'] ?? '#DC2626');
      $badge    = is_object($plan) ? $plan->badge : ($plan['badge'] ?? null);

      $icons = [
        'starter'    => 'fas fa-seedling',
        'business'   => 'fas fa-briefcase',
        'ong'        => 'fas fa-hands-helping',
        'academy'    => 'fas fa-graduation-cap',
        'enterprise' => 'fas fa-building',
      ];
      $icon = $icons[$slug] ?? 'fas fa-cube';
      $isFeatured   = $slug === 'business';
      $isEnterprise = $slug === 'enterprise';
    @endphp

    <div class="plan-card {{ $isFeatured ? 'featured' : '' }} {{ $isEnterprise ? 'enterprise' : '' }} slide-up">

      @if($badge)
      <span class="plan-badge" style="background:{{ $color }}">{{ $badge }}</span>
      @endif

      <div class="plan-icon" style="background:{{ $color }}22;">
        <i class="{{ $icon }}" style="color:{{ $color }}"></i>
      </div>

      <div class="plan-name">{{ $name }}</div>
      <div class="plan-target"><i class="fas fa-users fa-xs"></i> {{ $target }}</div>
      <div class="plan-desc">{{ $desc }}</div>

      @if($isEnterprise)
        <div class="plan-price">
          <span class="amount">Sur mesure</span>
        </div>
      @else
        <div class="plan-price" x-data>
          <span class="currency">{{ $currency }}</span>
          <span class="amount" x-text="$store.billing?.yearly
              ? '{{ number_format($yearly/12, 0, '.', ',') }}'
              : '{{ number_format($monthly, 0, '.', ',') }}'">
            {{ number_format($monthly, 0, '.', ',') }}
          </span>
          <span class="period">/mois</span>
        </div>
        <div style="font-size:.74rem;color:#94a3b8;margin-top:-.9rem;margin-bottom:1.3rem;" x-data>
          <span x-show="$store.billing?.yearly">
            Facturé {{ $currency }} {{ number_format($yearly, 0, '.', ',') }}/an
          </span>
        </div>
      @endif

      <ul class="plan-features">
        @foreach((array)$features as $f)
        <li><i class="fas fa-check-circle fa-xs"></i> {{ $f }}</li>
        @endforeach
      </ul>

      @if($isEnterprise)
        <a href="https://wa.me/50948174124" target="_blank" rel="noopener"
           class="plan-btn plan-btn-outline">
          <i class="fas fa-comment-dots"></i> Contacter l'équipe
        </a>
      @else
        <a href="{{ route('home') }}#reservation"
           class="plan-btn {{ $isFeatured ? 'plan-btn-primary' : 'plan-btn-outline' }}">
          {{ $isFeatured ? 'Commencer maintenant' : 'Choisir ce plan' }}
        </a>
      @endif
    </div>
    @endforeach
  </div>

  {{-- Comparison note --}}
  <div style="text-align:center;margin-top:3rem;color:#64748b;font-size:.85rem;">
    <i class="fas fa-shield-alt" style="color:#10B981;margin-right:.4rem;"></i>
    Toutes les offres incluent : mise en place incluse · support email · mises à jour gratuites · hébergement sécurisé (SSL)
  </div>
</section>

{{-- ── FEATURE COMPARISON TABLE ── --}}
<section style="padding:60px 1.5rem;background:#f8fafc;">
  <div style="max-width:1100px;margin:0 auto;">
    <div class="gv-head center" style="margin-bottom:2.5rem;">
      <span class="gv-stag"><i class="fas fa-table"></i> Comparatif</span>
      <h2 class="gv-h2">Que comprend chaque plan ?</h2>
    </div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:.87rem;">
        <thead>
          <tr style="background:#0f172a;color:#fff;">
            <th style="padding:.85rem 1rem;text-align:left;border-radius:10px 0 0 0;font-weight:700;">Fonctionnalité</th>
            @foreach(['Starter','Business','ONG','Academy','Enterprise'] as $h)
            <th style="padding:.85rem .6rem;text-align:center;{{ $h==='Business' ? 'background:#DC2626;' : '' }}">{{ $h }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @php $rows = [
            ['POS (caisse)','1','Illimité','2','—','Illimité'],
            ['CRM Clients','50','500','200','—','Illimité'],
            ['Facturation','✓','✓','✓','—','✓'],
            ['Gestion RH','—','✓','✓','—','✓'],
            ['Inventaire','—','✓','—','—','✓'],
            ['Module Dons/ONG','—','—','✓','—','✓'],
            ['Gestion cours','—','—','—','✓','✓'],
            ['Inscriptions en ligne','—','—','—','✓','✓'],
            ['WhatsApp auto','—','✓','✓','✓','✓'],
            ['API REST','—','—','—','—','✓'],
            ['Multi-branches','—','—','—','—','✓'],
            ['Support','Email','Prioritaire','Prioritaire','Email','Account Manager'],
          ]; @endphp
          @foreach($rows as $i => $row)
          <tr style="{{ $i%2===0 ? 'background:#fff;' : 'background:#f8fafc;' }}">
            @foreach($row as $j => $cell)
            @php
              $isCheck = $cell === '✓';
              $isNo    = $cell === '—';
              $isHead  = $j === 0;
            @endphp
            <td style="padding:.7rem 1rem;border-bottom:1px solid #e5e7eb;{{ $isHead ? 'font-weight:600;color:#0f172a;' : 'text-align:center;' }}{{ !$isHead && $j===2 ? 'background:rgba(220,38,38,.04);' : '' }}">
              @if($isCheck)
                <i class="fas fa-check" style="color:#10B981;font-size:1rem;"></i>
              @elseif($isNo)
                <span style="color:#d1d5db;">—</span>
              @else
                {{ $cell }}
              @endif
            </td>
            @endforeach
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>

{{-- ── FAQ ── --}}
<section class="faq-section">
  <div class="faq-wrap">
    <div class="gv-head center" style="margin-bottom:2.5rem;">
      <span class="gv-stag"><i class="fas fa-question-circle"></i> Questions fréquentes</span>
      <h2 class="gv-h2">Tout ce que vous voulez savoir</h2>
    </div>

    @php $faqs = [
      ['q'=>'Puis-je changer de plan plus tard ?', 'a'=>'Oui ! Vous pouvez monter ou descendre de plan à tout moment. La différence de prix est calculée au prorata.'],
      ['q'=>'Comment fonctionne l\'essai gratuit ?', 'a'=>'Tous nos plans incluent 14 jours d\'essai gratuit sans carte bancaire. Vous pouvez tester toutes les fonctionnalités avant de vous engager.'],
      ['q'=>'Vos prix sont-ils en USD ou HTG ?', 'a'=>'Nos tarifs sont affichés en HTG (gourdes haïtiennes). Pour les paiements en USD, le taux du jour s\'applique. Nous acceptons MonCash, NatCash, PayPal et virement bancaire.'],
      ['q'=>'Le support est-il disponible en créole ?', 'a'=>'Absolument ! Notre équipe de support parle français, créole haïtien et anglais. Nous répondons via WhatsApp, email et téléphone.'],
      ['q'=>'Puis-je obtenir une démo personnalisée ?', 'a'=>'Oui, contactez-nous sur WhatsApp ou par email pour planifier une démonstration gratuite adaptée à votre secteur d\'activité.'],
      ['q'=>'Les données sont-elles sécurisées ?', 'a'=>'Vos données sont hébergées sur des serveurs sécurisés (SSL/TLS), avec des sauvegardes quotidiennes. Nous respectons les bonnes pratiques RGPD.'],
    ]; @endphp

    @foreach($faqs as $i => $faq)
    <div class="faq-item">
      <button class="faq-q" onclick="toggleFaq({{ $i }}, this)">
        {{ $faq['q'] }}
        <i class="fas fa-plus"></i>
      </button>
      <div class="faq-a" id="faq-{{ $i }}">{{ $faq['a'] }}</div>
    </div>
    @endforeach
  </div>
</section>

{{-- ── CTA ── --}}
<section class="cta-bottom">
  <span class="gv-stag" style="border-color:rgba(220,38,38,.3);"><i class="fas fa-rocket"></i> Prêt à démarrer ?</span>
  <h2>Lancez votre business<br>avec GOVIBE ERP</h2>
  <p>14 jours d'essai gratuit · Sans engagement · Installation en 24h</p>
  <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
    <a href="{{ route('home') }}#reservation" class="gv-btn-prim">
      <i class="fas fa-play-circle"></i> Démarrer l'essai gratuit
    </a>
    <a href="https://wa.me/50948174124?text=Bonjour%20GOVIBE%2C%20je%20voudrais%20en%20savoir%20plus%20sur%20vos%20plans%20ERP"
       target="_blank" rel="noopener" class="gv-btn-outline" style="color:#fff;border-color:rgba(255,255,255,.25);">
      <i class="fab fa-whatsapp"></i> WhatsApp
    </a>
  </div>
</section>
@endsection

@section('scripts')
<script>
/* Billing toggle uses Alpine global store */
document.addEventListener('alpine:init', () => {
  Alpine.store('billing', { yearly: false });
});

/* Sync both toggle buttons */
document.querySelectorAll('.billing-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const cur = btn.classList.contains('yearly');
    document.querySelectorAll('.billing-toggle').forEach(b => b.classList.toggle('yearly', !cur));
    if (window.Alpine) {
      Alpine.store('billing').yearly = !cur;
    }
  });
});

/* FAQ accordion */
function toggleFaq(idx, btn) {
  const panel = document.getElementById('faq-' + idx);
  const isOpen = panel.classList.contains('open');
  document.querySelectorAll('.faq-a').forEach(p => p.classList.remove('open'));
  document.querySelectorAll('.faq-q').forEach(b => b.classList.remove('open'));
  if (!isOpen) {
    panel.classList.add('open');
    btn.classList.add('open');
  }
}
</script>
@endsection
