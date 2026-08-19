#!/usr/bin/env bash
#
# LOUVIA — pouse yon script Laravel ki sou VPS la nan yon depo GitHub prive.
#
# Fèt pou moun ki sou telefòn: yon sèl kòmand, epi li poze kesyon yo youn
# apre lòt. Li JANM voye .env ni vendor/ — li verifye epi li kanpe si sa
# ta rive.
#
# Kouri l konsa (yon sèl liy):
#   curl -sL https://raw.githubusercontent.com/Rooseveltfhr/govibe/claude/govibe-ai-architecture-kyhgvp/tools/pouse-script.sh | bash
#
set -uo pipefail

RED=$'\033[31m'; GRN=$'\033[32m'; YLW=$'\033[33m'; BLU=$'\033[36m'; DIM=$'\033[2m'; OFF=$'\033[0m'

say()  { printf '%s\n' "$*"; }
ok()   { printf '%s✓%s %s\n' "$GRN" "$OFF" "$*"; }
warn() { printf '%s!%s %s\n' "$YLW" "$OFF" "$*"; }
die()  { printf '%s✕ %s%s\n' "$RED" "$*" "$OFF" >&2; exit 1; }
head2(){ printf '\n%s── %s%s\n' "$BLU" "$*" "$OFF"; }

command -v git >/dev/null || die "git pa enstale sou sèvè a."

# Script la resevwa kesyon yo sou tèminal la, paske stdin okipe pa curl.
[ -r /dev/tty ] || die "Kouri script sa a nan yon vrè tèminal (Termius), se pa nan yon script otomatik."

ask() { # ask <mesaj> <non-varyab> [-s pou kache]
  local prompt="$1" var="$2" silent="${3:-}" value=""
  printf '%s' "$prompt"
  if [ "$silent" = "-s" ]; then read -rs value </dev/tty; printf '\n'; else read -r value </dev/tty; fi
  printf -v "$var" '%s' "$value"
}

# ── 1. Jwenn aplikasyon Laravel yo ──────────────────────────────────────────
head2 "1. Chèche aplikasyon Laravel yo"

mapfile -t APPS < <(find /home -maxdepth 7 -name artisan -not -path "*/vendor/*" 2>/dev/null | xargs -r -n1 dirname | sort -u)

[ ${#APPS[@]} -eq 0 ] && die "Okenn aplikasyon Laravel jwenn anba /home."

for i in "${!APPS[@]}"; do
  d="${APPS[$i]}"
  dom=$(printf '%s' "$d" | grep -oE 'domains/[^/]+' | cut -d/ -f2)
  ver=$(cd "$d" && php artisan --version 2>/dev/null | head -1)
  printf '  %s[%d]%s %s\n' "$BLU" "$((i+1))" "$OFF" "$d"
  printf '      %s%s · %s%s\n' "$DIM" "${dom:-?}" "${ver:-Laravel ?}" "$OFF"
done

printf '\n'
ask 'Kilès pou pouse? (nimewo) : ' PICK
IDX=$((PICK-1))
[ "$IDX" -ge 0 ] 2>/dev/null && [ -n "${APPS[$IDX]:-}" ] || die "Chwa ki pa valab."

APP="${APPS[$IDX]}"
cd "$APP" || die "Pa ka antre nan $APP"
ok "Chwazi: $APP"

# ── 2. .gitignore ───────────────────────────────────────────────────────────
head2 "2. Pwoteje sekrè yo"

if [ ! -f .gitignore ] || ! grep -qx '.env' .gitignore 2>/dev/null; then
  cat >> .gitignore <<'EOF'
/vendor
/node_modules
/storage/logs
/storage/framework/cache
/storage/framework/sessions
/storage/framework/views
/public/storage
/storage/*.key
.env
.env.*
!.env.example
auth.json
*.log
.DS_Store
EOF
  ok ".gitignore ekri"
else
  ok ".gitignore deja bon"
fi

# ── 3. Depo lokal ───────────────────────────────────────────────────────────
head2 "3. Prepare depo a"

[ -d .git ] || { git init -q; ok "git init"; }
git config user.email >/dev/null 2>&1 || git config user.email "dev@govibeht.com"
git config user.name  >/dev/null 2>&1 || git config user.name  "GOVIBE"

git add -A

# ── 4. Kontwòl sekirite — pa negosyab ───────────────────────────────────────
head2 "4. Kontwòl sekirite"

LEAK=$(git diff --cached --name-only | grep -E '(^|/)\.env$|(^|/)\.env\.|(^|/)auth\.json$|(^|/)vendor/|\.pem$|\.key$' || true)

if [ -n "$LEAK" ]; then
  printf '%s\n' "$LEAK" | head -10
  git reset -q
  die "Fichye sansib nan lis la. Anyen pa voye. Verifye .gitignore epi rekòmanse."
fi

COUNT=$(git diff --cached --name-only | wc -l)
SIZE=$(git diff --cached --name-only -z | xargs -0 -r du -ch 2>/dev/null | tail -1 | cut -f1)
ok "Okenn .env, okenn vendor/ — $COUNT fichye ($SIZE)"

if [ "$COUNT" -eq 0 ]; then
  warn "Anyen nouvo pou voye (petèt ou deja fè l)."
else
  git commit -q -m "Import $(basename "$APP") depi VPS" && ok "Commit fèt"
fi

# ── 5. GitHub ───────────────────────────────────────────────────────────────
head2 "5. Voye sou GitHub"

say "${DIM}Kreye yon depo PRIVE sou github.com anvan (New repository).${OFF}"
ask 'Non depo a (egz. Rooseveltfhr/govibe-callcenter) : ' REPO
[ -n "$REPO" ] || die "Non depo a obligatwa."

say "${DIM}Jeton: github.com → Settings → Developer settings → Personal access tokens${OFF}"
say "${DIM}(dwa « repo ». Li p ap parèt pandan w ap tape l.)${OFF}"
ask 'Jeton GitHub : ' TOKEN -s
[ -n "$TOKEN" ] || die "Jeton an obligatwa."

BRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || echo main)
[ "$BRANCH" = "HEAD" ] && BRANCH=main
git branch -M "$BRANCH" 2>/dev/null || true

git remote remove origin 2>/dev/null || true
git remote add origin "https://github.com/${REPO}.git"

say "Ap voye… (sa ka pran kèk minit)"

if git push -q "https://x-access-token:${TOKEN}@github.com/${REPO}.git" "$BRANCH" 2>&1; then
  ok "Voye! → https://github.com/${REPO}"
else
  die "Push la echwe. Verifye non depo a, jeton an, epi ke depo a egziste."
fi

# Jeton an pa rete nan .git/config
git remote set-url origin "https://github.com/${REPO}.git"
unset TOKEN

head2 "Fini"
say "Depo a: ${GRN}https://github.com/${REPO}${OFF}"
say "Di Claude non depo a epi l ap kòmanse travay ladan l."
say ""
say "${DIM}Pou dezyèm script la, rekòmanse menm kòmand lan epi chwazi lòt nimewo a.${OFF}"
