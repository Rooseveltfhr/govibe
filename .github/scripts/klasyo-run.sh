#!/usr/bin/env bash
# KLASYO — MODULE A : renommer "Bank" -> "Paiement Manuel" (+ description) et styliser les tuiles de paiement,
# sur les 2 checkouts (cours + abonnement). Clé interne 'bank' conservée. Backup + compile Blade + rollback.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
FILES=(
  "$P/resources/views/frontend/student/cart/checkout.blade.php"
  "$P/resources/views/frontend/student/subscription/checkout.blade.php"
)

# --- fragments (heredocs cités: aucune expansion) ---
OLD_LABEL="$(mktemp)"; cat > "$OLD_LABEL" <<'EOF'
<span class="font-16 color-heading font-medium">{{ __('Bank') }}</span>
EOF
NEW_LABEL="$(mktemp)"; cat > "$NEW_LABEL" <<'EOF'
<span>
                                                    <span class="font-16 color-heading font-medium me-2">{{ __('Paiement Manuel') }}</span>
                                                    <span class="k-manual-badge">{{ __('Validation 5–24 h') }}</span>
                                                    <span class="font-14 d-block mt-1 k-manual-desc">Ce mode de paiement est manuel : choisissez une méthode, envoyez le montant, puis un administrateur l'approuvera sous 5 à 24 h. Le montant sera ensuite crédité sur votre compte KLASYO.</span>
                                                </span>
EOF
CSS_ANCHOR='<div class="payment-method-box bg-white">'
CSS_BLOCK="$(mktemp)"; cat > "$CSS_BLOCK" <<'EOF'
<div class="payment-method-box bg-white">
<style data-klasyo-pay>
.payment-method-box .payment-method-card-box{border:1.6px solid #e3e9f5;border-radius:14px;padding:16px 18px 16px 44px;transition:.15s;background:#fff}
.payment-method-box .payment-method-card-box:hover{border-color:#b9ccf7;box-shadow:0 6px 18px rgba(29,78,216,.08)}
.payment-method-box .payment-method-card-box:has(input.form-check-input:checked){border-color:#1d4ed8;box-shadow:0 8px 22px rgba(29,78,216,.15);background:#f7faff}
.payment-method-box .form-check-input:checked{background-color:#1d4ed8;border-color:#1d4ed8}
.payment-method-box .k-manual-badge{display:inline-block;background:#e8f8ee;color:#166534;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;vertical-align:middle}
.payment-method-box .k-manual-desc{color:#5b6675;line-height:1.5;max-width:560px}
</style>
EOF

# --- PHP: édition sûre d'un fichier ---
PHPX="$(mktemp /tmp/klasyo_payedit_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
[$_, $view, $oldF, $newF, $anchor, $cssF] = $argv;
$html = file_get_contents($view);
$old = rtrim(file_get_contents($oldF), "\n");
$new = rtrim(file_get_contents($newF), "\n");
$css = rtrim(file_get_contents($cssF), "\n");

if (strpos($html, 'k-manual-desc') !== false) { echo "SKIP: déjà modifié\n"; exit(0); }

$changed = 0;
// 1) libellé Bank -> Paiement Manuel + description
$n = substr_count($html, $old);
if ($n === 1) { $html = str_replace($old, $new, $html); $changed++; echo "  libellé: OK\n"; }
else { echo "  libellé: introuvable/ambigu ($n) — ignoré\n"; }

// 2) CSS après l'ouverture .payment-method-box (une fois)
$na = substr_count($html, $anchor);
if ($na >= 1 && strpos($html, 'data-klasyo-pay') === false) {
  // remplacer seulement la 1re occurrence
  $pos = strpos($html, $anchor);
  $html = substr($html,0,$pos) . $css . substr($html, $pos + strlen($anchor));
  $changed++; echo "  CSS: injecté\n";
} else { echo "  CSS: ancre=$na (non injecté)\n"; }

if ($changed === 0) { echo "  aucun changement\n"; exit(0); }

// garde-fou: logique interne intacte
foreach (['value="bank"','name="bank_id"','name="deposit_slip"'] as $must) {
  if (strpos($html, $must) === false) { fwrite(STDERR, "ABORT: '$must' perdu\n"); exit(3); }
}
file_put_contents($view.'.klasyo-new', $html);
echo "  écrit .klasyo-new (".strlen($html)." o)\n";
PHPEOF

# --- compilation Blade réelle + php -l ---
COMPILE="$(mktemp /tmp/klasyo_compile_XXXX.php)"
cat > "$COMPILE" <<'PHPEOF'
<?php
require $argv[2].'/vendor/autoload.php';
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
$files = new Filesystem();
$bc = new BladeCompiler($files, sys_get_temp_dir());
$php = $bc->compileString(file_get_contents($argv[1]));
file_put_contents($argv[3], $php);
echo "compiled ".strlen($php)." o\n";
PHPEOF

for CO in "${FILES[@]}"; do
  echo "==================== $CO ===================="
  [ -f "$CO" ] || { echo "  (absent, ignoré)"; continue; }
  STAMP="$(date +%Y%m%d-%H%M%S)"; BK="$CO.bak-paymanual-$STAMP"
  cp -p "$CO" "$BK"
  php "$PHPX" "$CO" "$OLD_LABEL" "$NEW_LABEL" "$CSS_ANCHOR" "$CSS_BLOCK" || { echo "  edit PHP échec"; continue; }
  [ -f "$CO.klasyo-new" ] || { echo "  (pas de nouveau contenu -> rien à faire)"; continue; }
  # compile-test du nouveau contenu
  OUT="/tmp/compiled_$$.php"
  if php "$COMPILE" "$CO.klasyo-new" "$P" "$OUT" 2>/tmp/cerr && php -l "$OUT" >/tmp/lint 2>&1; then
    echo "  compile Blade: OK / $(cat /tmp/lint)"
    mv "$CO.klasyo-new" "$CO"
    echo "  DEPLOYÉ. backup: $BK"
    grep -c 'Paiement Manuel' "$CO" | sed 's/^/  occurrences Paiement Manuel: /'
  else
    echo "  (!) COMPILE/ LINT ECHEC -> ROLLBACK"; cat /tmp/cerr /tmp/lint 2>/dev/null | head -8
    rm -f "$CO.klasyo-new"
  fi
done

# purge cache vues (best-effort, Blade recompile de toute façon au prochain hit)
( cd "$P" && timeout 40 php artisan view:clear 2>&1 | head -3 ) || echo "  (view:clear ignoré)"

rm -f "$OLD_LABEL" "$NEW_LABEL" "$CSS_BLOCK" "$PHPX" "$COMPILE" /tmp/compiled_*.php
echo
echo "== FIN MODULE A =="
