#!/usr/bin/env python3
"""Valide le partiel Blade « Mes services » avant tout déploiement.

Leçon apprise à la dure : la première version de ce fichier a provoqué un
500 en production alors que la compilation Blade passait. Le validateur
d'origine RETIRAIT les commentaires {{-- --}} avant de vérifier — or Blade
fait l'inverse : il compile les directives D'ABORD, puis supprime les
commentaires. Un nom de directive cité en documentation était donc exécuté
pour de bon, ouvrait un bloc PHP parasite, avalait les déclarations de
variables, et donnait « Undefined variable $gvpCards ».

Ce validateur reproduit donc l'ordre réel de Blade. Il sort en code 1 au
moindre problème, pour bloquer le déploiement.
"""

import os
import re
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
BLADE = os.path.join(HERE, "dashboard-services.blade.php")
CSS = os.path.join(HERE, "dashboard-services.css")

# Directives que Blade compile réellement. « media » et « import » n'en font
# pas partie : Blade laisse passer un @directive inconnu tel quel, ce qui rend
# le CSS (@media, @import) sans danger.
BLADE_DIRECTIVES = {
    "php", "endphp", "if", "elseif", "else", "endif", "unless", "endunless",
    "foreach", "endforeach", "forelse", "empty", "endforelse", "for", "endfor",
    "while", "endwhile", "section", "endsection", "show", "stop", "append",
    "yield", "extends", "include", "includeIf", "includeWhen", "each",
    "push", "endpush", "prepend", "stack", "once", "endonce",
    "auth", "endauth", "guest", "endguest", "isset", "endisset",
    "json", "csrf", "method", "props", "verbatim", "endverbatim",
    "can", "endcan", "cannot", "endcannot", "error", "enderror",
    "switch", "case", "break", "default", "endswitch", "continue",
}

problems = []


def fail(msg):
    problems.append(msg)


src = open(BLADE, encoding="utf-8").read()

# ── 1. LA RÈGLE QUI NOUS A COÛTÉ UN 500 ────────────────────────────────────
# Aucun nom de directive Blade ne doit figurer dans un commentaire {{-- --}}.
for m in re.finditer(r"\{\{--(.*?)--\}\}", src, re.S):
    for d in re.finditer(r"@(\w+)", m.group(1)):
        if d.group(1) in BLADE_DIRECTIVES:
            fail(
                f"directive @{d.group(1)} citée dans un commentaire Blade — "
                "elle SERA exécutée (les commentaires sont supprimés après "
                "la compilation des directives)"
            )

# ── 2. Syntaxe PHP de chaque bloc, commentaires INCLUS ─────────────────────
blocks = re.findall(r"@php\b(.*?)@endphp", src, re.S)
if not blocks:
    fail("aucun bloc PHP trouvé — le fichier est-il complet ?")
for i, code in enumerate(blocks, 1):
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False,
                                     encoding="utf-8") as f:
        f.write("<?php\n" + code)
        path = f.name
    r = subprocess.run(["php", "-l", path], capture_output=True, text=True)
    if r.returncode:
        fail(f"bloc PHP #{i} invalide : {(r.stdout + r.stderr).strip()[:200]}")
    os.unlink(path)

# ── 3. Équilibre des directives ────────────────────────────────────────────
for open_d, close_d in [("@if", "@endif"), ("@forelse", "@endforelse"),
                        ("@php", "@endphp"), ("@foreach", "@endforeach")]:
    n_open = len(re.findall(re.escape(open_d) + r"\b", src))
    n_close = len(re.findall(re.escape(close_d) + r"\b", src))
    if n_open != n_close:
        fail(f"{open_d}/{close_d} déséquilibrés : {n_open}/{n_close}")

# ── 4. Toute route appelée doit être protégée par Route::has() ─────────────
called = set(re.findall(r"route\(\s*'([^']+)'", src))
guarded = set(re.findall(r"Route::has\(\s*'([^']+)'", src))
unguarded = called - guarded
if unguarded:
    fail(f"routes appelées sans Route::has() : {sorted(unguarded)}")

# ── 5. Pièges Blade connus du projet ───────────────────────────────────────
if re.search(r"@(?:json|if|foreach|forelse)\([^)]*(?:fn\s*\(|=>\s*\[)", src):
    fail("fonction fléchée ou tableau littéral dans une directive Blade")

# ── 6. Le CSS ─────────────────────────────────────────────────────────────
# Il est désormais déployé en fichier statique, donc hors de portée de Blade.
# On garde malgré tout le contrôle : si quelqu'un le réintègre un jour dans la
# vue, un nom de directive qui traîne dedans casserait de nouveau la page.
# On réutilise LA MÊME liste que pour le Blade — c'est d'avoir maintenu deux
# listes divergentes qui a laissé passer « @push » et cassé la production.
css = open(CSS, encoding="utf-8").read()
for d in re.finditer(r"@(\w+)", css):
    if d.group(1) in BLADE_DIRECTIVES:
        fail(
            f"le CSS contient @{d.group(1)}, un nom de directive Blade — "
            "inoffensif tant que le fichier reste statique, fatal s'il est "
            "réintégré dans la vue"
        )
if "{{" in css:
    fail("le CSS contient « {{ », que Blade interpréterait comme un écho")
if css.count("{") != css.count("}"):
    fail(f"accolades CSS déséquilibrées : {css.count('{')}/{css.count('}')}")

# ── Verdict ────────────────────────────────────────────────────────────────
if problems:
    print("VALIDATION ÉCHOUÉE :")
    for p in problems:
        print("  ✗", p)
    sys.exit(1)

print("Validation OK")
print(f"  blocs PHP        : {len(blocks)}")
print(f"  routes appelées  : {len(called)}, toutes protégées")
print(f"  règles CSS       : {css.count('{')}")
