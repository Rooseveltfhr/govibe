"""
Ekstraksyon entansyon depi yon transkripsyon.

Se dezyèm etap la — epi se li ki konte. Yon transkripsyon ka gen erè epi
entansyon an rete kòrèk. Nou sèvi ak yon LLM bon mache (menm youn ajan an
pral sèvi an pwodiksyon), atravè yon endpoint konpatib OpenAI — kidonk li
mache ak paswèl GOVIBE a menm jan ak OpenAI dirèk.
"""

from __future__ import annotations

import json
import os
import re

import requests

INTENTS = {
    "order": "kliyan an vle kòmande yon manje oswa yon pwodwi",
    "price": "kliyan an mande pri oswa si yon bagay disponib",
    "booking": "kliyan an vle pran, chanje oswa anile yon randevou",
    "info": "kliyan an mande orè, adrès, livrezon oswa lòt enfòmasyon",
    "complaint": "kliyan an gen yon plent oswa yon pwoblèm",
    "other": "anyen nan lis la",
}

SLOTS = ["item", "qty", "date", "time", "person", "phone", "notes"]

SYSTEM_PROMPT = f"""Ou se yon sistèm ki li mesaj kliyan an kreyòl ayisyen pou yon biznis.
Transkripsyon an SOTI NAN YON SISTÈM VWA epi li ka gen erè — konprann entansyon an
malgre mo ki mal transkri.

Retounen SÈLMAN yon objè JSON, san okenn lòt tèks, ak fòm sa a:
{{"intent": "<youn nan: {', '.join(INTENTS)}>", "slots": {{"item": "", "qty": "", "date": "", "time": "", "person": "", "phone": "", "notes": ""}}}}

Entansyon yo:
{chr(10).join(f'- {k}: {v}' for k, v in INTENTS.items())}

Règ:
- Kite yon slot vid ("") si mesaj la pa di l. Pa envante anyen.
- "qty" se yon chif ("2", pa "de").
- Si transkripsyon an pa fè sans ditou, retounen intent "other"."""


class IntentExtractor:
    def __init__(self, model: str | None = None, base_url: str | None = None):
        self.base_url = (base_url or os.environ.get("LLM_BASE_URL", "https://api.openai.com/v1")).rstrip("/")
        self.model = model or os.environ.get("LLM_MODEL", "gpt-4o-mini")
        self.api_key = os.environ.get("LLM_API_KEY") or os.environ.get("OPENAI_API_KEY", "")

    def available(self) -> bool:
        return bool(self.api_key)

    def extract(self, transcript: str) -> tuple[str, dict[str, str]]:
        """Retounen (entansyon, slots). Pa janm leve — yon echèk = ('other', {})."""
        if not transcript.strip():
            return "other", {}

        try:
            response = requests.post(
                f"{self.base_url}/chat/completions",
                headers={
                    "Authorization": f"Bearer {self.api_key}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": self.model,
                    "temperature": 0,
                    "messages": [
                        {"role": "system", "content": SYSTEM_PROMPT},
                        {"role": "user", "content": transcript},
                    ],
                },
                timeout=90,
            )
            response.raise_for_status()
            content = response.json()["choices"][0]["message"]["content"]
        except Exception as exc:  # noqa: BLE001 — yon echèk pa dwe kanpe evalyasyon an
            print(f"    ⚠ ekstraksyon entansyon echwe: {exc}")
            return "other", {}

        return self._parse(content)

    @staticmethod
    def _parse(content: str) -> tuple[str, dict[str, str]]:
        """Modèl yo souvan vlope JSON nan ```json — nou dekoupe l."""
        match = re.search(r"\{.*\}", content, re.DOTALL)
        if not match:
            return "other", {}

        try:
            payload = json.loads(match.group(0))
        except json.JSONDecodeError:
            return "other", {}

        intent = str(payload.get("intent", "other")).strip().lower()
        if intent not in INTENTS:
            intent = "other"

        raw_slots = payload.get("slots") or {}
        slots = {
            key: str(raw_slots.get(key, "")).strip()
            for key in SLOTS
            if str(raw_slots.get(key, "")).strip()
        }

        return intent, slots
