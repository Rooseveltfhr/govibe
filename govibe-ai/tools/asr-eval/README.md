# Evalyasyon ASR kreyòl — pwotokòl ak zouti

> **Poukisa etap sa a anvan tout lòt bagay.** Tout pwodwi ajan vokal LOUVIA a chita
> sou yon sèl kesyon: **èske yon machin ka konprann sa yon Ayisyen di?** Si repons lan
> se non, ou dwe konnen l nan de semèn, pa nan sis mwa. Se sa zouti sa a fè.
>
> Metrik ki deside a **se pa WER** (pousantaj mo ki mal). Se **siksè tach**: èske
> entansyon an ak enfòmasyon kritik yo (kisa, konbyen, kilè) rive kòrèk — paske se
> sa ki pèmèt ajan an *aji*. Yon transkripsyon ka gen 3 mo mal epi kòmand lan rete bon.

---

## 1. Kote pou mete 100 nòt vokal yo

**Dirèkteman soti nan WhatsApp — pa gen konvèsyon pou fè.**

```
tools/asr-eval/
├── clips/            ← metè tout fichye odyo yo LA
│   ├── n001.opus
│   ├── n002.opus
│   └── …
└── manifest.csv      ← yon liy pa clip (kopye manifest.example.csv)
```

`clips/` **pa monte nan git** (gade `.gitignore`) — se vwa moun reyèl, se done pèsonèl.

### Kijan pou soti nòt yo nan WhatsApp

**Sou telefòn (pi senp):**
1. Louvri konvèsasyon an → peze lontan sou nòt vokal la → **Transfere** → **Sove nan Fichye** / Drive.
2. Oswa: meni konvèsasyon an → **Ekspòte diskisyon** → **Ak medya** → li bay yon `.zip` ak tout odyo yo.

**Sou òdinatè:** WhatsApp Desktop → menm bagay, oswa kopye depi
`WhatsApp/Media/WhatsApp Voice Notes/`.

Fòma WhatsApp la se `.opus` nan yon veso `.ogg`. **Tout founisè nan zouti a aksepte l dirèk.**
Si ou gen `.m4a` (iPhone) oswa `.mp3`, yo mache tou.

### Chanje non yo an lo

```bash
cd tools/asr-eval/clips
i=1; for f in *.opus *.ogg *.m4a; do
  [ -e "$f" ] || continue
  mv "$f" "$(printf 'n%03d' $i).${f##*.}"; i=$((i+1))
done
```

---

## 2. Kijan pou kolekte 100 nòt yo — sa ki fè oswa kraze tès la

### ⚠️ Erè ki pi grav: fè moun **li** yon tèks

Si ou bay yon moun yon fraz pou li li, li pale dousman, klè, san bri. ASR a fè 95%.
Apre sa ou lanse pwodwi a epi li fè 60% sou vrè moun. **Ou pran yon move desizyon
ak yon bon chif.**

**Règ la: nòt vokal reyèl, oswa moun k ap pale natirèlman sou yon sitiyasyon reyèl.**
Di yon machann: « voye yon nòt bay biznis la tankou ou ta fè l tout bon ». Pa ba li mo yo.

### Repatisyon 100 clip yo

| Dimansyon | Repatisyon | Poukisa |
|---|---|---|
| **Entansyon** | 25 kòmand · 20 pri/disponiblite · 20 randevou · 20 enfòmasyon · 15 plent | Se sa ajan an pral tande chak jou |
| **Rejyon** | 40 Lwès · 20 Nò · 20 Sid · 20 Atibonit/Sant | Aksan yo diferan — se la sistèm nan kraze |
| **Sèks** | ~50 fanm · ~50 gason | Vwa fanm souvan pi mal transkri |
| **Bri** | 30 kalm · 40 mwayen (lari, vantilatè) · 30 fò (mache, moto, dèlko) | Reyalite Ayiti — pa yon estidyo |
| **Longè** | 40 kout (<10 s) · 40 mwayen (10-30 s) · 20 long (>30 s) | Nòt long yo pi difisil |
| **Melanj lang** | omwen 25 ak fransè/anglè melanje | « Mwen vle *commander* de poul » — sa komen anpil epi li kraze ASR |

Si ou pa ka rive 100 kounye a, **kòmanse ak 40** men kenbe repatisyon an.
40 clip byen chwazi di w plis pase 100 clip ki tout soti Pòtoprens nan yon sal kalm.

### Otorizasyon — pa sote sa

Se vwa moun reyèl. Anvan ou sèvi ak yon nòt:

- Di moun nan sa w ap fè: « n ap teste yon sistèm ki koute kreyòl, èske m ka sèvi ak nòt ou a? »
- Pa mete non, nimewo telefòn oswa enfòmasyon peman nan `transcript_ref`.
- `clips/` rete deyò git. Pa janm pouse l, pa janm mete l nan yon Drive piblik.
- Efase clip yo apre desizyon an pran.

---

## 3. Ranpli manifest la

Kopye `manifest.example.csv` → `manifest.csv`, epi mete yon liy pa clip.
Ou ka ranpli l nan Excel oswa Google Sheets epi ekspòte an CSV.

```csv
file,intent,item,qty,date,time,person,phone,notes,region,gender,noise,duration_s,code_switch,transcript_ref
n001.opus,order,poul,2,,,,,,ouest,f,mwayen,9,non,mwen ta renmen de poul ak yon diri kole
```

- **`intent`** — `order` · `price` · `booking` · `info` · `complaint` · `other`
- **`item` / `qty` / `date` / `time`** — se **slot kritik** yo. Si youn ladan yo mal,
  ajan an ta fè yon move aksyon → clip la konte kòm echèk.
- **`transcript_ref`** — sa moun nan di vre. **Opsyonèl**, men ranpli l sou omwen
  30 clip: se sa ki bay WER la pou konpare founisè yo.

---

## 4. Kouri tès la

```bash
cd tools/asr-eval
pip install -r requirements.txt

# 1. Verifye chèn nan mache (zewo apèl API, zewo depans)
python3 asr_eval.py --dry-run

# 2. Verifye lojik notasyon an
python3 -m unittest test_scoring

# 3. Mete kle yo (pa janm nan git)
export OPENAI_API_KEY=sk-…        # Whisper + ekstraksyon entansyon
export GLADIA_API_KEY=…           # opsyonèl
export HF_API_KEY=hf_…            # opsyonèl — modèl kreyòl fine-tuned

# 4. Kòmanse sou 5 clip pou verifye tout bagay
python3 asr_eval.py --providers openai-whisper --limit 5 -v

# 5. Tout tès la
python3 asr_eval.py --providers openai-whisper,gladia,huggingface
```

Rezilta: `rapo.html` (rapò vizyèl) + `rapo.json` (done brit).

### Founisè ki disponib

| Kle | Sa li ye | Kle API |
|---|---|---|
| `openai-whisper` | Baz konparezon. ⚠️ Rechèch montre Whisper fè *pi mal* an kreyòl pase modèl espesyalize | `OPENAI_API_KEY` |
| `gladia` | Anonse sipò kreyòl ayisyen | `GLADIA_API_KEY` |
| `huggingface` | Modèl kominotè fine-tuned pou kreyòl — **kandida ki pi pwomèt la** | `HF_API_KEY` + `HF_ASR_MODEL` |
| `elevenlabs` | Scribe — bon sou lang ki gen tikras done, pi chè | `ELEVENLABS_API_KEY` |

Ekstraksyon entansyon an pase nan yon endpoint konpatib OpenAI — kidonk ou ka
pwente l sou paswèl GOVIBE a menm:

```bash
export LLM_BASE_URL=https://studio.govibeht.com/api/v1
export LLM_MODEL=deepseek/deepseek-chat
export LLM_API_KEY=…
```

---

## 5. Kijan pou li rezilta a

```
Founisè                   Siksè tach   Entansyon     WER    Latans
hf:kreyol-tuned                  82%         91%     31%    1715 ms
openai-whisper                   54%         66%     48%    2315 ms
```

**Sèl kolòn ki deside a se « Siksè tach ».**

| Rezilta | Desizyon |
|---|---|
| **≥ 85%** | **Ale.** Ajan an ka aji dirèk sou sa li konprann. |
| **70-85%** | **Ale, ak konfimasyon.** Ajan an dwe mande « Ou vle 2 poul, se sa? » anvan chak aksyon. Sa toujou yon bon pwodwi. |
| **< 70%** | **Pa ale konsa.** Opsyon: fine-tune yon modèl sou done ou (gade pi ba), redwi domèn nan (yon sèl sektè), oswa kòmanse ak tèks sèlman. |

Rapò a montre tou **kote** li kraze — pa rejyon, pa nivo bri, pa sèks. Si Nò a
a 45% epi Lwès la a 88%, ou pa gen yon pwoblèm jeneral: ou gen yon pwoblèm aksan,
epi solisyon an se plis done Nò, pa yon lòt founisè.

### Si rezilta a ba

Pa abandone touswit — nan lòd:

1. **Konfimasyon obligatwa** anvan chak aksyon (pote 70% rive nan yon pwodwi ki sèvi).
2. **Redwi domèn nan**: yon ajan restoran ki konnen sèlman 40 pla gen anpil mwens
   posibilite pou l twonpe l pase yon ajan jeneral.
3. **Fine-tune**: 100 clip ou yo ak transkripsyon kòrèk yo se deja yon jèm dataset.
   Ak 5-10 èdtan odyo transkri, yon Whisper fine-tuned monte anpil. **Epi dataset sa a
   vin pou ou — se yon barikad konkiran w yo pa genyen.**
4. **Vwa antre → tèks soti**: mwens risk, li deja rezoud pwoblèm nan pou moun ki pi
   alèz pale pase ekri.

---

## 6. Sa ki nan dosye a

| Fichye | Wòl |
|---|---|
| `kreyol.py` | Nòmalizasyon kreyòl (kontraksyon, aksan, chif). Pi — teste. |
| `scoring.py` | WER, entansyon, slot, siksè tach, vèdik. Pi — teste. |
| `providers.py` | Adaptè ASR. Ajoute yon founisè = yon klas isit. |
| `intent.py` | Ekstraksyon entansyon ak yon LLM. |
| `report.py` | Rapò konsòl + HTML. |
| `asr_eval.py` | Oganizatè + liy kòmand. |
| `test_scoring.py` | 28 tès sou lojik la. Kouri yo anvan ou depanse. |
