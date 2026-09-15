"""
Notasyon evalyasyon ASR.

Pwen kle a: **WER pa metrik ki deside**. Yon transkripsyon ki gen 20% mo mal
ka toujou bay bon entansyon an — epi se entansyon an ki fè ajan an aji.
Nou kalkile WER pou referans, men desizyon ale/pa ale a chita sou
`task_success` : entansyon kòrèk **epi** tout slot kritik yo kòrèk.

Modil pi — okenn E/S, okenn rezo.
"""

from __future__ import annotations

from dataclasses import dataclass, field

from kreyol import normalize, tokens

# Slot ki ka fè ajan an fè yon move aksyon si yo mal.
# (« konbyen » ak « kilè » mal = move kòmand, move randevou.)
CRITICAL_SLOTS = {"item", "qty", "date", "time"}


def word_error_rate(reference: str, hypothesis: str) -> float:
    """
    WER klasik (Levenshtein sou mo), sou tèks nòmalize.
    Retounen yon pwopòsyon: 0.0 = pafè, 1.0 = tout mo mal.
    """
    ref = tokens(reference)
    hyp = tokens(hypothesis)

    if not ref:
        return 0.0 if not hyp else 1.0

    # Levenshtein ak de liy sèlman (memwa O(n)).
    previous = list(range(len(hyp) + 1))

    for i, ref_word in enumerate(ref, start=1):
        current = [i]
        for j, hyp_word in enumerate(hyp, start=1):
            cost = 0 if ref_word == hyp_word else 1
            current.append(min(
                previous[j] + 1,        # efasman
                current[j - 1] + 1,     # ensèsyon
                previous[j - 1] + cost, # sibstitisyon
            ))
        previous = current

    return previous[-1] / len(ref)


def slot_matches(expected: str, actual: str) -> bool:
    """
    Konpare de valè slot apre nòmalizasyon kreyòl.
    « de » ak « 2 » matche. « poul » ak « Poul » matche.
    """
    if expected is None or actual is None:
        return False

    a = normalize(str(expected))
    b = normalize(str(actual))

    if a == b:
        return True

    # Yon slot ki genyen lòt la konte (ASR bay « diri kole » pou « diri »).
    if a and b and (a in b or b in a):
        return True

    return False


@dataclass
class ClipResult:
    """Rezilta yon sèl nòt vokal pou yon sèl founisè."""

    clip: str
    provider: str
    expected_intent: str
    predicted_intent: str | None = None
    expected_slots: dict[str, str] = field(default_factory=dict)
    predicted_slots: dict[str, str] = field(default_factory=dict)
    transcript: str = ""
    reference: str = ""
    latency_ms: int = 0
    error: str | None = None
    meta: dict[str, str] = field(default_factory=dict)

    @property
    def intent_ok(self) -> bool:
        if self.predicted_intent is None:
            return False
        return normalize(self.predicted_intent) == normalize(self.expected_intent)

    @property
    def critical_slots(self) -> dict[str, str]:
        return {k: v for k, v in self.expected_slots.items() if k in CRITICAL_SLOTS}

    @property
    def slots_ok(self) -> bool:
        """Tout slot kritik yo kòrèk (si pa gen slot kritik, se vre)."""
        for key, expected in self.critical_slots.items():
            if not slot_matches(expected, self.predicted_slots.get(key, "")):
                return False
        return True

    @property
    def slot_accuracy(self) -> float | None:
        """Pwopòsyon slot (tout, pa sèlman kritik) ki kòrèk."""
        if not self.expected_slots:
            return None
        hits = sum(
            1 for k, v in self.expected_slots.items()
            if slot_matches(v, self.predicted_slots.get(k, ""))
        )
        return hits / len(self.expected_slots)

    @property
    def task_success(self) -> bool:
        """Metrik ki deside: ajan an ta ka aji kòrèkteman."""
        return self.error is None and self.intent_ok and self.slots_ok

    @property
    def wer(self) -> float | None:
        """None si nou pa gen transkripsyon referans pou clip sa a."""
        if not self.reference:
            return None
        return word_error_rate(self.reference, self.transcript)


@dataclass
class ProviderSummary:
    provider: str
    total: int = 0
    errors: int = 0
    intent_ok: int = 0
    task_ok: int = 0
    latency_ms_total: int = 0
    wer_values: list[float] = field(default_factory=list)
    slot_values: list[float] = field(default_factory=list)

    @property
    def intent_accuracy(self) -> float:
        return self.intent_ok / self.total if self.total else 0.0

    @property
    def task_success_rate(self) -> float:
        return self.task_ok / self.total if self.total else 0.0

    @property
    def avg_latency_ms(self) -> int:
        usable = self.total - self.errors
        return round(self.latency_ms_total / usable) if usable else 0

    @property
    def avg_wer(self) -> float | None:
        return sum(self.wer_values) / len(self.wer_values) if self.wer_values else None

    @property
    def avg_slot_accuracy(self) -> float | None:
        return sum(self.slot_values) / len(self.slot_values) if self.slot_values else None

    @property
    def verdict(self) -> str:
        """
        Sèy yo soti nan odit la:
          ≥ 85%  → ale, ajan an ka aji dirèk
          70-85% → ale, men ak yon etap konfimasyon anvan chak aksyon
          < 70%  → chanje plan
        """
        rate = self.task_success_rate
        if rate >= 0.85:
            return "go"
        if rate >= 0.70:
            return "go_with_confirmation"
        return "no_go"


def summarize(results: list[ClipResult]) -> dict[str, ProviderSummary]:
    """Agrege rezilta yo pa founisè."""
    summaries: dict[str, ProviderSummary] = {}

    for result in results:
        summary = summaries.setdefault(result.provider, ProviderSummary(result.provider))
        summary.total += 1

        if result.error is not None:
            summary.errors += 1
            continue

        summary.latency_ms_total += result.latency_ms

        if result.intent_ok:
            summary.intent_ok += 1
        if result.task_success:
            summary.task_ok += 1

        wer = result.wer
        if wer is not None:
            summary.wer_values.append(wer)

        slots = result.slot_accuracy
        if slots is not None:
            summary.slot_values.append(slots)

    return summaries


def breakdown(results: list[ClipResult], provider: str, key: str) -> dict[str, tuple[int, int]]:
    """
    Rezilta pa yon dimansyon (rejyon, bri, sèks…) pou yon founisè.
    Retounen {valè: (siksè, total)} — se konsa ou wè KOTE li kraze.
    """
    out: dict[str, tuple[int, int]] = {}

    for result in results:
        if result.provider != provider:
            continue
        bucket = result.meta.get(key) or "?"
        hits, total = out.get(bucket, (0, 0))
        out[bucket] = (hits + (1 if result.task_success else 0), total + 1)

    return dict(sorted(out.items()))
