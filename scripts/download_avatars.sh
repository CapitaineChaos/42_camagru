#!/usr/bin/env bash
# Downloads RoboHash avatars for the 26 seed users.
# - Random number of images between 1 and 12 per user (aaa…zzz)
# - Naming: aaa-1.png, aaa-2.png, ...
# - Idempotent: existing images are skipped, manifest is rebuilt each time.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
OUTDIR="$SCRIPT_DIR/avatars"

# Si le manifest existe déjà, les avatars sont déjà là - on ne re-télécharge pas.
if [[ -f "$OUTDIR/manifest.json" ]]; then
    echo "Avatars déjà présents ($OUTDIR/manifest.json), rien à faire."
    echo "Pour re-télécharger : rm -rf scripts/avatars && make seed-avatars"
    exit 0
fi

mkdir -p "$OUTDIR"

for letter in {a..z}; do
    triple="${letter}${letter}${letter}"

    # Count files already downloaded for this user
    existing=$(find "$OUTDIR" -maxdepth 1 -name "${triple}-*.png" 2>/dev/null | wc -l)

    if [[ "$existing" -gt 0 ]]; then
        echo "  ~ $triple → $existing photo(s) déjà présentes, skip"
    else
        count=$(( RANDOM % 12 + 1 ))
        echo "  $triple → téléchargement de $count photo(s)..."
        for i in $(seq 1 "$count"); do
            url="https://robohash.org/${triple}-${i}?size=400x400&set=set1&bgset=bg1"
            curl -sfL "$url" -o "${OUTDIR}/${triple}-${i}.png"
            echo "    + ${triple}-${i}.png"
        done
    fi
done

# == Rebuild manifest.json from existing files =================================
echo ""
echo "Génération de manifest.json..."
{
    printf '{\n'
    first=true
    for letter in {a..z}; do
        triple="${letter}${letter}${letter}"
        count=$(find "$OUTDIR" -maxdepth 1 -name "${triple}-*.png" 2>/dev/null | wc -l)
        if [[ "$count" -gt 0 ]]; then
            [[ "$first" == false ]] && printf ',\n'
            printf '  "%s": %d' "$triple" "$count"
            first=false
        fi
    done
    printf '\n}\n'
} > "$OUTDIR/manifest.json"

echo "  → $OUTDIR/manifest.json"
echo ""
echo "Terminé - avatars dans $OUTDIR"
