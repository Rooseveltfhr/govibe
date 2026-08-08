#!/usr/bin/env python3
"""
Evalyasyon ASR kreyòl — kouri 100 nòt vokal sou plizyè founisè epi mezire
si ajan an ta ka AJI kòrèkteman.

    python3 asr_eval.py --providers openai-whisper,gladia,huggingface
    python3 asr_eval.py --dry-run          # verifye chèn nan san depanse anyen

Desizyon an chita sou `task_success`, pa sou WER. Gade scoring.py.
"""

from __future__ import annotations

import argparse
import csv
import json
import sys
from dataclasses import asdict
from pathlib import Path

from intent import IntentExtractor
from report import render_console, render_html
from scoring import ClipResult, summarize

HERE = Path(__file__).parent
SLOT_COLUMNS = ["item", "qty", "date", "time", "person", "phone", "notes"]
META_COLUMNS = ["region", "gender", "noise", "duration_s", "code_switch"]


def load_manifest(path: Path, clips_dir: Path) -> list[dict]:
    """Li manifest CSV la epi verifye chak fichye egziste vre."""
    if not path.exists():
        raise SystemExit(
            f"Manifest pa jwenn: {path}\n"
            f"Kopye manifest.example.csv → manifest.csv epi ranpli l."
        )

    rows: list[dict] = []
    missing: list[str] = []

    with path.open(newline="", encoding="utf-8") as handle:
        for line, row in enumerate(csv.DictReader(handle), start=2):
            filename = (row.get("file") or "").strip()
            if not filename or filename.startswith("#"):
                continue

            audio = clips_dir / filename
            if not audio.exists():
                missing.append(f"  liy {line}: {filename}")
                continue

            expected_slots = {
                key: row[key].strip()
                for key in SLOT_COLUMNS
                if row.get(key) and row[key].strip()
            }

            rows.append({
                "path": audio,
                "clip": filename,
                "intent": (row.get("intent") or "other").strip().lower(),
                "slots": expected_slots,
                "reference": (row.get("transcript_ref") or "").strip(),
                "meta": {k: (row.get(k) or "").strip() for k in META_COLUMNS},
            })

    if missing:
        print(f"⚠ {len(missing)} fichye nan manifest la pa nan clips/ :", file=sys.stderr)
        print("\n".join(missing[:10]), file=sys.stderr)

    if not rows:
        raise SystemExit("Zewo clip itilizab. Mete fichye odyo yo nan clips/.")

    return rows


def run(rows: list[dict], providers, extractor: IntentExtractor, verbose: bool) -> list[ClipResult]:
    results: list[ClipResult] = []

    for index, row in enumerate(rows, start=1):
        print(f"[{index}/{len(rows)}] {row['clip']}")

        for provider in providers:
            result = ClipResult(
                clip=row["clip"],
                provider=provider.name,
                expected_intent=row["intent"],
                expected_slots=row["slots"],
                reference=row["reference"],
                meta=row["meta"],
            )

            try:
                transcription = provider.transcribe(row["path"])
                result.transcript = transcription.text
                result.latency_ms = transcription.latency_ms

                intent, slots = extractor.extract(transcription.text)
                result.predicted_intent = intent
                result.predicted_slots = slots

                mark = "✓" if result.task_success else "✗"
                print(f"  {mark} {provider.name:<22} {intent:<10} {transcription.latency_ms:>6} ms")
                if verbose:
                    print(f"      « {transcription.text[:110]} »")

            except Exception as exc:  # noqa: BLE001 — yon founisè ki tonbe pa dwe kanpe rès la
                result.error = str(exc)[:200]
                print(f"  ! {provider.name:<22} ERÈ: {result.error}")

            results.append(result)

    return results


def fixtures() -> list[ClipResult]:
    """
    Done fo pou `--dry-run`: verifye chèn notasyon an ak rapò a mache
    anvan ou depanse yon santim nan API.
    """
    # (entansyon atann, entansyon jwenn, slot atann, slot jwenn, rejyon, bri,
    #  transkripsyon referans, transkripsyon ASR)
    samples = [
        ("order", "order", {"item": "poul", "qty": "2"}, {"item": "poul", "qty": "2"},
         "ouest", "kalm",
         "mwen ta renmen de poul ak yon diri kole",
         # 3 mo mal sou 8 (WER 37%) — men entansyon ak slot yo rete kòrèk.
         "mwen ta renmen de poul ak yon diri kole a"),
        ("order", "order", {"item": "diri", "qty": "1"}, {"item": "diri kole", "qty": "1"},
         "ouest", "mwayen",
         "ban mwen yon diri kole souple",
         "ban mwen yon diri kole souple"),
        ("order", "price", {"item": "griyo", "qty": "3"}, {"item": "griyo"},
         "nord", "fò",
         "mwen vle twa griyo pou mwen pote ale",
         "mwen we twa gri yo pou mwen pote ale"),
        ("price", "price", {"item": "bannann"}, {"item": "bannann"},
         "sud", "mwayen",
         "konbyen bannann peze a koute",
         "konbyen bannann peze a koute"),
        ("booking", "booking", {"date": "25", "time": "10"}, {"date": "25", "time": "10"},
         "ouest", "kalm",
         "mwen vle yon randevou 25 la a dis zè",
         "mwen vle yon randevou 25 la a dis zè"),
        ("booking", "booking", {"date": "3", "time": "14"}, {"date": "3", "time": "16"},
         "nord", "fò",
         "eske nou lib 3 la a de zè",
         "eske nou lib 3 la a sis zè"),
        ("info", "info", {}, {},
         "sud", "mwayen",
         "kilè nou fèmen jodi a",
         "kile nou femen jodi a"),
        ("complaint", "complaint", {}, {},
         "ouest", "kalm",
         "kòmand mwen an poko rive depi inè",
         "koman mwen an poko rive depi inè"),
    ]

    out: list[ClipResult] = []
    for provider, penalty in (("openai-whisper", 2), ("hf:kreyol-tuned", 0)):
        for i, sample in enumerate(samples):
            expected, predicted, exp_slots, pred_slots, region, noise, ref, hyp = sample
            # Founisè « baz » la echwe sou kèk clip anplis — done demonstrasyon.
            degrade = penalty and i % 4 == 0
            out.append(ClipResult(
                clip=f"n{i:03d}.opus",
                provider=provider,
                expected_intent=expected,
                predicted_intent="other" if degrade else predicted,
                expected_slots=exp_slots,
                predicted_slots={} if degrade else pred_slots,
                transcript=hyp,
                reference=ref,
                latency_ms=1400 + i * 90 + penalty * 300,
                meta={"region": region, "noise": noise},
            ))
    return out


def main() -> int:
    parser = argparse.ArgumentParser(description="Evalyasyon ASR kreyòl pou LOUVIA")
    parser.add_argument("--providers", default="openai-whisper",
                        help="lis separe pa vigil: openai-whisper,gladia,huggingface,elevenlabs")
    parser.add_argument("--manifest", default="manifest.csv")
    parser.add_argument("--clips", default="clips")
    parser.add_argument("--out", default="rapo", help="prefiks fichye rezilta yo")
    parser.add_argument("--limit", type=int, default=0, help="teste sèlman N premye clip yo")
    parser.add_argument("--dry-run", action="store_true", help="done fo, zewo apèl rezo")
    parser.add_argument("-v", "--verbose", action="store_true", help="montre transkripsyon yo")
    args = parser.parse_args()

    if args.dry_run:
        print("── Mòd tès: done fo, okenn apèl API ──\n")
        results = fixtures()
    else:
        import providers as provider_module

        clips_dir = (HERE / args.clips).resolve()
        rows = load_manifest((HERE / args.manifest).resolve(), clips_dir)

        if args.limit:
            rows = rows[: args.limit]

        print(f"Clip: {len(rows)}")
        selected = provider_module.build([p.strip() for p in args.providers.split(",") if p.strip()])

        if not selected:
            raise SystemExit("Okenn founisè ki gen kle API. Mete yo nan anviwònman an.")

        extractor = IntentExtractor()
        if not extractor.available():
            raise SystemExit("Mete LLM_API_KEY (oswa OPENAI_API_KEY) pou ekstraksyon entansyon an.")

        print(f"Founisè: {', '.join(p.name for p in selected)}\n")
        results = run(rows, selected, extractor, args.verbose)

    summaries = summarize(results)

    render_console(summaries, results)

    html_path = HERE / f"{args.out}.html"
    html_path.write_text(render_html(summaries, results), encoding="utf-8")

    json_path = HERE / f"{args.out}.json"
    json_path.write_text(
        json.dumps([asdict(r) for r in results], ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print(f"\nRapò: {html_path.name} · done brit: {json_path.name}")

    # Kòd sòti: 0 si omwen yon founisè pase, 1 si okenn — itil nan CI.
    return 0 if any(s.verdict != "no_go" for s in summaries.values()) else 1


if __name__ == "__main__":
    raise SystemExit(main())
