#!/bin/bash
# ============================================================
# GOVIBEPAY — nettoyage des sauvegardes accumulées par
# .github/workflows/govibepay-merge.yml
#
# Ce que ce workflow crée, à CHAQUE exécution (pas une fois) :
#   ~/domains/govibepay.com/brand-backup-<horodatage>/
#   ~/domains/govibepay.com/assets-backup-<horodatage>/
#   ~/domains/govibepay.com/govibepay-merge-backup-<horodatage>/
#   *.bak-govibepay, .env.bak-brand-<horodatage>
#
# La logique de restauration du workflow (ls -dt ... | head -1) ne lit
# JAMAIS que la PLUS RÉCENTE sauvegarde de chaque type — toutes les
# précédentes ne servent plus à rien dès qu'une nouvelle existe.
#
# Usage, DIRECTEMENT SUR LE VPS (jamais depuis ce dépôt) :
#   bash cleanup-govibepay-backups.sh                  # rapport seul, ne touche à rien
#   bash cleanup-govibepay-backups.sh --keep 2          # garde les 2 plus récentes de chaque type
#   bash cleanup-govibepay-backups.sh --apply           # supprime réellement (garde 1 par défaut)
#   bash cleanup-govibepay-backups.sh --keep 2 --apply  # supprime, en gardant les 2 plus récentes
# ============================================================
set -euo pipefail

DOM="${DOM:-$HOME/domains/govibepay.com}"
KEEP=1
APPLY=0

while [ $# -gt 0 ]; do
    case "$1" in
        --apply) APPLY=1; shift ;;
        --keep) KEEP="${2:?valeur manquante pour --keep}"; shift 2 ;;
        *) echo "Option inconnue : $1" >&2; exit 1 ;;
    esac
done

[ -d "$DOM" ] || { echo "ERREUR: $DOM introuvable — ajuster DOM=... si govibepay.com vit ailleurs."; exit 1; }

total_octets=0
total_items=0

# Traite un motif de nom (ex. "brand-backup-*") : liste du plus récent au
# plus ancien (tri par date de modification, robuste aux noms avec espaces),
# garde les $KEEP premiers, propose/supprime le reste.
traiter() {
    local motif="$1" libelle="$2"
    local items=()
    while IFS= read -r f; do items+=("$f"); done < <(
        find "$DOM" -maxdepth 1 -name "$motif" -printf '%T@ %p\n' 2>/dev/null \
            | sort -rn | cut -d' ' -f2-
    )

    local n="${#items[@]}"
    [ "$n" -eq 0 ] && { echo "[$libelle] aucune trouvée."; return; }

    echo "[$libelle] $n trouvée(s), on garde les $KEEP plus récente(s) :"
    local i=0
    for f in "${items[@]}"; do
        i=$((i + 1))
        local taille taille_h date_h
        taille=$(du -sb "$f" 2>/dev/null | cut -f1)
        taille_h=$(du -sh "$f" 2>/dev/null | cut -f1)
        date_h=$(stat -c '%y' "$f" 2>/dev/null | cut -d'.' -f1)
        if [ "$i" -le "$KEEP" ]; then
            echo "  GARDÉE   $taille_h  $date_h  $f"
        else
            total_octets=$((total_octets + ${taille:-0}))
            total_items=$((total_items + 1))
            if [ "$APPLY" -eq 1 ]; then
                echo "  SUPPRIMÉE $taille_h  $date_h  $f"
                rm -rf -- "$f"
            else
                echo "  À SUPPRIMER (dry-run) $taille_h  $date_h  $f"
            fi
        fi
    done
}

traiter "brand-backup-*" "brand-backup"
traiter "assets-backup-*" "assets-backup"
traiter "govibepay-merge-backup-*" "govibepay-merge-backup"

echo ""
if [ "$APPLY" -eq 1 ]; then
    echo "✓ $total_items dossier(s) supprimé(s), espace libéré : $(numfmt --to=iec "$total_octets" 2>/dev/null || echo "${total_octets} octets")"
else
    echo "Dry-run : $total_items dossier(s) seraient supprimés, $(numfmt --to=iec "$total_octets" 2>/dev/null || echo "${total_octets} octets") libérés."
    echo "Relancer avec --apply pour les supprimer réellement."
fi
