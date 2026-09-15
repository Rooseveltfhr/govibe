"""Rapò konsòl ak HTML pou evalyasyon ASR la."""

from __future__ import annotations

import html
from datetime import datetime

from scoring import ClipResult, ProviderSummary, breakdown

VERDICT_LABEL = {
    "go": ("ALE", "Ajan an ka aji dirèk sou rezilta a."),
    "go_with_confirmation": (
        "ALE AK KONFIMASYON",
        "Bon ase, men ajan an dwe konfime anvan chak aksyon : « Ou vle 2 poul, se sa ? »",
    ),
    "no_go": ("PA ALE", "Kalite a twò ba. Chanje founisè, fine-tune, oswa chanje plan."),
}

VERDICT_COLOR = {"go": "#0E7A46", "go_with_confirmation": "#9A6600", "no_go": "#B22F26"}


def _pct(value: float | None) -> str:
    return "—" if value is None else f"{value * 100:.0f}%"


def render_console(summaries: dict[str, ProviderSummary], results: list[ClipResult]) -> None:
    print("\n" + "=" * 74)
    print("REZILTA — metrik ki deside a se « Siksè tach », pa WER")
    print("=" * 74)
    print(f"{'Founisè':<24}{'Siksè tach':>12}{'Entansyon':>12}{'WER':>8}{'Latans':>10}")
    print("-" * 74)

    ranked = sorted(summaries.values(), key=lambda s: s.task_success_rate, reverse=True)

    for summary in ranked:
        print(
            f"{summary.provider:<24}"
            f"{_pct(summary.task_success_rate):>12}"
            f"{_pct(summary.intent_accuracy):>12}"
            f"{_pct(summary.avg_wer):>8}"
            f"{summary.avg_latency_ms:>8} ms"
        )
        if summary.errors:
            print(f"{'':<24}({summary.errors} erè sou {summary.total})")

    if not ranked:
        return

    best = ranked[0]
    label, advice = VERDICT_LABEL[best.verdict]

    print("-" * 74)
    print(f"\n  DESIZYON : {label}  ({best.provider} — {_pct(best.task_success_rate)})")
    print(f"  {advice}\n")

    # Kote li kraze — se sa ki di w ki done pou kolekte apre.
    for dimension in ("region", "noise"):
        buckets = breakdown(results, best.provider, dimension)
        if len(buckets) > 1:
            parts = [f"{k} {_pct(hits / total)}" for k, (hits, total) in buckets.items() if total]
            print(f"  Pa {dimension}: {' · '.join(parts)}")


def render_html(summaries: dict[str, ProviderSummary], results: list[ClipResult]) -> str:
    ranked = sorted(summaries.values(), key=lambda s: s.task_success_rate, reverse=True)
    best = ranked[0] if ranked else None
    generated = datetime.now().strftime("%Y-%m-%d %H:%M")

    rows = "\n".join(
        f"""<tr>
          <td><b>{html.escape(s.provider)}</b></td>
          <td class="n" style="color:{VERDICT_COLOR[s.verdict]};font-weight:600">{_pct(s.task_success_rate)}</td>
          <td class="n">{_pct(s.intent_accuracy)}</td>
          <td class="n">{_pct(s.avg_slot_accuracy)}</td>
          <td class="n">{_pct(s.avg_wer)}</td>
          <td class="n">{s.avg_latency_ms} ms</td>
          <td class="n">{s.errors}/{s.total}</td>
        </tr>"""
        for s in ranked
    )

    breakdown_html = ""
    if best:
        for dimension, title in (("region", "Pa rejyon"), ("noise", "Pa nivo bri"), ("gender", "Pa sèks")):
            buckets = breakdown(results, best.provider, dimension)
            if len(buckets) <= 1:
                continue
            cells = "".join(
                f'<div class="cell"><b>{_pct(hits / total)}</b><span>{html.escape(k)} ({total})</span></div>'
                for k, (hits, total) in buckets.items() if total
            )
            breakdown_html += f'<h3>{title}</h3><div class="cells">{cells}</div>'

    failures = [r for r in results if best and r.provider == best.provider and not r.task_success]
    failure_rows = "\n".join(
        f"""<tr>
          <td class="mono">{html.escape(r.clip)}</td>
          <td>{html.escape(r.expected_intent)} → <b>{html.escape(r.predicted_intent or '—')}</b></td>
          <td class="mono small">{html.escape(str(r.expected_slots))}</td>
          <td class="mono small">{html.escape(str(r.predicted_slots))}</td>
          <td class="small">{html.escape((r.transcript or r.error or '')[:120])}</td>
        </tr>"""
        for r in failures[:40]
    )

    verdict_block = ""
    if best:
        label, advice = VERDICT_LABEL[best.verdict]
        verdict_block = f"""
        <div class="verdict" style="border-left-color:{VERDICT_COLOR[best.verdict]}">
          <p class="kick" style="color:{VERDICT_COLOR[best.verdict]}">Desizyon</p>
          <h2>{label} — {html.escape(best.provider)} a {_pct(best.task_success_rate)}</h2>
          <p>{html.escape(advice)}</p>
        </div>"""

    return f"""<!doctype html>
<html lang="ht"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Evalyasyon ASR kreyòl — LOUVIA</title>
<style>
  :root {{ --bg:#F7F8FA; --card:#fff; --rule:#D8DFE7; --ink:#0D1219; --dim:#59667A; }}
  @media (prefers-color-scheme: dark) {{
    :root {{ --bg:#0A0E13; --card:#121820; --rule:#26303C; --ink:#E3EAF2; --dim:#8595A8; }}
  }}
  * {{ box-sizing:border-box }}
  body {{ margin:0;background:var(--bg);color:var(--ink);
    font-family:system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6 }}
  .wrap {{ max-width:1000px;margin:0 auto;padding:2.5rem 1.25rem 5rem }}
  h1 {{ font-size:1.9rem;margin:0 0 .3rem;letter-spacing:-.02em }}
  h2 {{ font-size:1.25rem;margin:0 }}
  h3 {{ font-size:.95rem;margin:1.5rem 0 .5rem;color:var(--dim) }}
  .kick {{ font-family:ui-monospace,monospace;font-size:.68rem;letter-spacing:.16em;
    text-transform:uppercase;color:var(--dim);margin:0 0 .4rem }}
  .verdict {{ background:var(--card);border:1px solid var(--rule);border-left:4px solid;
    border-radius:6px;padding:1.2rem 1.4rem;margin:1.5rem 0;display:flex;
    flex-direction:column;gap:.4rem }}
  .verdict p:last-child {{ color:var(--dim);font-size:.93rem;margin:0 }}
  table {{ width:100%;border-collapse:collapse;background:var(--card);
    border:1px solid var(--rule);border-radius:6px;overflow:hidden;font-size:.9rem }}
  th,td {{ text-align:left;padding:.6rem .8rem;border-bottom:1px solid var(--rule) }}
  th {{ font-family:ui-monospace,monospace;font-size:.64rem;letter-spacing:.1em;
    text-transform:uppercase;color:var(--dim) }}
  tr:last-child td {{ border-bottom:0 }}
  td.n {{ font-family:ui-monospace,monospace;font-variant-numeric:tabular-nums;text-align:right }}
  .mono {{ font-family:ui-monospace,monospace;font-size:.8rem }}
  .small {{ font-size:.8rem;color:var(--dim) }}
  .cells {{ display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:.5rem }}
  .cell {{ background:var(--card);border:1px solid var(--rule);border-radius:6px;
    padding:.6rem .7rem;display:flex;flex-direction:column }}
  .cell b {{ font-family:ui-monospace,monospace;font-size:1.15rem }}
  .cell span {{ font-size:.72rem;color:var(--dim) }}
  .tw {{ overflow-x:auto }}
  footer {{ margin-top:3rem;padding-top:1rem;border-top:1px solid var(--rule);
    font-size:.75rem;color:var(--dim) }}
</style></head><body><div class="wrap">
  <p class="kick">LOUVIA · GOVIBE Innovation Hub</p>
  <h1>Evalyasyon ASR kreyòl</h1>
  <p class="small">{len(results)} mezi · {generated}</p>

  {verdict_block}

  <h3>Konparezon founisè</h3>
  <div class="tw"><table>
    <thead><tr>
      <th>Founisè</th><th style="text-align:right">Siksè tach</th>
      <th style="text-align:right">Entansyon</th><th style="text-align:right">Slot</th>
      <th style="text-align:right">WER</th><th style="text-align:right">Latans</th>
      <th style="text-align:right">Erè</th>
    </tr></thead>
    <tbody>{rows}</tbody>
  </table></div>
  <p class="small" style="margin-top:.6rem">
    <b>Siksè tach</b> = entansyon kòrèk <b>epi</b> tout slot kritik (item, qty, date, time)
    kòrèk. Se sèl metrik ki di si ajan an ka aji. WER la se referans sèlman.
  </p>

  {breakdown_html}

  <h3>Echèk yo — {html.escape(best.provider) if best else ''} ({len(failures)})</h3>
  <div class="tw"><table>
    <thead><tr><th>Clip</th><th>Entansyon</th><th>Slot atann</th><th>Slot jwenn</th><th>Transkripsyon</th></tr></thead>
    <tbody>{failure_rows or '<tr><td colspan="5" class="small">Okenn echèk 🎉</td></tr>'}</tbody>
  </table></div>

  <footer>Sèy: ≥85% ale · 70-85% ale ak konfimasyon · &lt;70% chanje plan.</footer>
</div></body></html>"""
