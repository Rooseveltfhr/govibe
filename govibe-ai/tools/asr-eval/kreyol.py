"""
Nòmalizasyon tèks kreyòl ayisyen pou konparezon.

Kreyòl ayisyen gen anpil varyasyon òtografik ki *pa* erè: yon moun ekri
« map », yon lòt « m ap », yon lòt « m'ap » — se menm bagay la. Si nou konpare
tèks brit, n ap konte erè ki pa erè, epi n ap rejte yon founisè ki bon.

Modil sa a pi (pa gen okenn E/S, okenn depandans) — se poutèt sa li teste
fasilman.
"""

from __future__ import annotations

import re
import unicodedata

# Kontraksyon kote fòm kole a PA yon lòt mo — espas la opsyonèl san danje.
# (« map », « lap », « wap », « poum »… pa egziste kòm mo apa.)
CONTRACTIONS = [
    (r"\bm ?ap\b", "mwen ap"),
    (r"\bw ?ap\b", "ou ap"),
    (r"\bl ?ap\b", "li ap"),
    (r"\by ?ap\b", "yo ap"),
    (r"\bm ?te\b", "mwen te"),
    (r"\bl ?te\b", "li te"),
    (r"\bn ?te\b", "nou te"),
    (r"\bpou ?m\b", "pou mwen"),
    (r"\bpou ?w\b", "pou ou"),
    (r"\bpou ?n\b", "pou nou"),
    (r"\bak ?w\b", "ak ou"),
    (r"\bavè ?m\b", "avèk mwen"),
    (r"\bavè ?w\b", "avèk ou"),
]

# Kontraksyon kote fòm kole a se yon VRÈ MO ki vle di lòt bagay.
# Espas la OBLIGATWA, sinon nou kraze mo ki komen nan biznis:
#   « poul » (manje) ≠ « pou l » (pou li)
#   « nap » (twal tab) ≠ « n ap » (nou ap)
#   « kap » (sèvolan) ≠ « k ap » (ki ap)
# Se yon bug sa a te koze: « de poul » te vin tounen « de pou li ».
SPACED_CONTRACTIONS = [
    (r"\bpou l\b", "pou li"),
    (r"\bn ap\b", "nou ap"),
    (r"\bk ap\b", "ki ap"),
]

# Mo ki ekri an de moso oswa an yon sèl — nou kole yo.
JOINED = [
    (r"\bki ?jan\b", "kijan"),
    (r"\bki ?sa\b", "kisa"),
    (r"\bpou ?ki ?sa\b", "poukisa"),
    (r"\bkou ?man\b", "kòman"),
    (r"\bkonb ?yen\b", "konbyen"),
    (r"\bkoun ?ye ?a\b", "kounye a"),
    (r"\btou ?swit\b", "touswit"),
    (r"\bkèk ?fwa\b", "kèkfwa"),
]

# Varyant kout ki rete kout (pwonon apre yon vèb) — nou pa touche yo,
# men nou nòmalize fòm ki ekri ak apostwòf.
APOSTROPHES = str.maketrans({"'": " ", "’": " ", "`": " "})

# Chif ekri an mo → chif. ASR souvan bay youn oswa lòt.
NUMBER_WORDS = {
    "zewo": "0", "youn": "1", "yon": "1", "en": "1", "de": "2", "twa": "3",
    "kat": "4", "senk": "5", "sis": "6", "sèt": "7", "set": "7", "uit": "8",
    "wit": "8", "nèf": "9", "nef": "9", "dis": "10", "onz": "11", "douz": "12",
    "trèz": "13", "katòz": "14", "kenz": "15", "sèz": "16", "disèt": "17",
    "dizuit": "18", "diznèf": "19", "ven": "20", "trant": "30", "karant": "40",
    "senkant": "50", "swasant": "60", "san": "100", "mil": "1000",
}


def strip_accents(text: str) -> str:
    """Retire aksan yo. ASR konfonn è/e ak ò/o tout tan — se pa yon vrè erè."""
    decomposed = unicodedata.normalize("NFD", text)
    return "".join(ch for ch in decomposed if unicodedata.category(ch) != "Mn")


def normalize(text: str, *, drop_accents: bool = True, map_numbers: bool = True) -> str:
    """
    Nòmalize yon tèks kreyòl pou konparezon.

    Lòd la enpòtan: apostwòf → espas anvan kontraksyon, sinon « m'ap » pa matche.
    """
    if not text:
        return ""

    out = text.lower()
    out = out.translate(APOSTROPHES)

    # Retire tout sa ki pa lèt, chif oswa espas.
    out = re.sub(r"[^\w\s]", " ", out, flags=re.UNICODE)
    out = re.sub(r"\s+", " ", out).strip()

    # Fòm ak espas obligatwa an premye, pandan espas la toujou la.
    for pattern, replacement in SPACED_CONTRACTIONS:
        out = re.sub(pattern, replacement, out)

    for pattern, replacement in CONTRACTIONS:
        out = re.sub(pattern, replacement, out)

    for pattern, replacement in JOINED:
        out = re.sub(pattern, replacement, out)

    if drop_accents:
        out = strip_accents(out)

    if map_numbers:
        # Apre strip_accents pou « sèt » ak « set » tou de matche.
        keys = {strip_accents(k) if drop_accents else k: v for k, v in NUMBER_WORDS.items()}
        out = " ".join(keys.get(word, word) for word in out.split())

    return re.sub(r"\s+", " ", out).strip()


def tokens(text: str, **kwargs) -> list[str]:
    normalized = normalize(text, **kwargs)
    return normalized.split() if normalized else []
