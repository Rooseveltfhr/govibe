"""
Adaptè founisè ASR.

Chak adaptè fè yon sèl bagay: pran yon fichye odyo, retounen tèks + latans.
Ajoute yon founisè = ajoute yon klas isit la epi mete l nan REGISTRY.

Kle API yo soti nan anviwònman an sèlman — pa janm nan kòd, pa janm nan git.
"""

from __future__ import annotations

import os
import time
from dataclasses import dataclass
from pathlib import Path

try:
    import requests
except ImportError:  # pragma: no cover
    raise SystemExit("Enstale depandans yo: pip install -r requirements.txt")


@dataclass
class Transcription:
    text: str
    latency_ms: int
    raw: dict | None = None


class AsrProvider:
    """Kontra a. `name` sèvi nan rapò a ak nan liy kòmand lan."""

    name = "base"
    #: Pri endikatif pou 1 minit odyo, an USD — pou konpare kou reyèl.
    price_per_minute_usd = 0.0

    def available(self) -> bool:
        raise NotImplementedError

    def transcribe(self, path: Path) -> Transcription:
        raise NotImplementedError

    # -- zouti pataje ------------------------------------------------------

    @staticmethod
    def _timed(fn):
        started = time.monotonic()
        result = fn()
        return result, int((time.monotonic() - started) * 1000)


class OpenAiWhisper(AsrProvider):
    """
    Whisper via API OpenAI. Baz konparezon an — men atansyon: rechèch montre
    Whisper fè PI MAL an kreyòl pase modèl espesyalize, paske kreyòl se yon
    lang ki gen tikras done. Nou mete l pou n gen yon referans.
    """

    name = "openai-whisper"
    price_per_minute_usd = 0.006

    def __init__(self, model: str = "whisper-1", language: str = "ht"):
        self.model = model
        self.language = language
        self.api_key = os.environ.get("OPENAI_API_KEY", "")

    def available(self) -> bool:
        return bool(self.api_key)

    def transcribe(self, path: Path) -> Transcription:
        def call():
            with path.open("rb") as handle:
                return requests.post(
                    "https://api.openai.com/v1/audio/transcriptions",
                    headers={"Authorization": f"Bearer {self.api_key}"},
                    files={"file": (path.name, handle, "application/octet-stream")},
                    data={"model": self.model, "language": self.language},
                    timeout=180,
                )

        response, latency = self._timed(call)
        response.raise_for_status()
        payload = response.json()

        return Transcription(payload.get("text", "").strip(), latency, payload)


class Gladia(AsrProvider):
    """
    Gladia — anonse sipò kreyòl ayisyen (asenkwon ak tan reyèl).
    Fonksyone an de tan: voye fichye a, apre sa poze kesyon sou rezilta a.
    """

    name = "gladia"
    price_per_minute_usd = 0.012

    def __init__(self, language: str = "ht"):
        self.language = language
        self.api_key = os.environ.get("GLADIA_API_KEY", "")

    def available(self) -> bool:
        return bool(self.api_key)

    def transcribe(self, path: Path) -> Transcription:
        headers = {"x-gladia-key": self.api_key}
        started = time.monotonic()

        with path.open("rb") as handle:
            upload = requests.post(
                "https://api.gladia.io/v2/upload",
                headers=headers,
                files={"audio": (path.name, handle, "application/octet-stream")},
                timeout=180,
            )
        upload.raise_for_status()
        audio_url = upload.json()["audio_url"]

        job = requests.post(
            "https://api.gladia.io/v2/pre-recorded",
            headers={**headers, "Content-Type": "application/json"},
            json={"audio_url": audio_url, "language": self.language},
            timeout=60,
        )
        job.raise_for_status()
        result_url = job.json()["result_url"]

        # Sondaj jiskaske travay la fini (maksimòm ~3 minit).
        for _ in range(90):
            poll = requests.get(result_url, headers=headers, timeout=60)
            poll.raise_for_status()
            payload = poll.json()
            status = payload.get("status")

            if status == "done":
                text = payload.get("result", {}).get("transcription", {}).get("full_transcript", "")
                latency = int((time.monotonic() - started) * 1000)
                return Transcription(text.strip(), latency, payload)

            if status == "error":
                raise RuntimeError(f"Gladia: {payload.get('error_code')}")

            time.sleep(2)

        raise TimeoutError("Gladia: rezilta a pa rive nan tan an")


class HuggingFaceModel(AsrProvider):
    """
    Nenpòt modèl ASR sou Hugging Face Inference — se konsa nou teste modèl
    kominotè ki fine-tuned espesyalman pou kreyòl (egz.
    `jsbeaudry/whisper-medium-oswald`). Se kandida ki pi pwomèt la.
    """

    name = "huggingface"
    price_per_minute_usd = 0.0  # depann de enstalasyon an

    def __init__(self, model_id: str | None = None):
        self.model_id = model_id or os.environ.get("HF_ASR_MODEL", "jsbeaudry/whisper-medium-oswald")
        self.api_key = os.environ.get("HF_API_KEY", "")
        self.name = f"hf:{self.model_id.split('/')[-1]}"

    def available(self) -> bool:
        return bool(self.api_key)

    def transcribe(self, path: Path) -> Transcription:
        def call():
            return requests.post(
                f"https://api-inference.huggingface.co/models/{self.model_id}",
                headers={
                    "Authorization": f"Bearer {self.api_key}",
                    "Content-Type": "audio/ogg",
                },
                data=path.read_bytes(),
                timeout=300,
            )

        response, latency = self._timed(call)
        response.raise_for_status()
        payload = response.json()

        if isinstance(payload, dict) and "error" in payload:
            raise RuntimeError(f"HuggingFace: {payload['error']}")

        text = payload.get("text", "") if isinstance(payload, dict) else str(payload)

        return Transcription(text.strip(), latency, payload if isinstance(payload, dict) else None)


class ElevenLabsScribe(AsrProvider):
    """ElevenLabs Scribe — bon sou lang ki gen tikras done, men pi chè."""

    name = "elevenlabs"
    price_per_minute_usd = 0.02

    def __init__(self, model_id: str = "scribe_v1"):
        self.model_id = model_id
        self.api_key = os.environ.get("ELEVENLABS_API_KEY", "")

    def available(self) -> bool:
        return bool(self.api_key)

    def transcribe(self, path: Path) -> Transcription:
        def call():
            with path.open("rb") as handle:
                return requests.post(
                    "https://api.elevenlabs.io/v1/speech-to-text",
                    headers={"xi-api-key": self.api_key},
                    files={"file": (path.name, handle, "application/octet-stream")},
                    data={"model_id": self.model_id, "language_code": "hat"},
                    timeout=180,
                )

        response, latency = self._timed(call)
        response.raise_for_status()
        payload = response.json()

        return Transcription(payload.get("text", "").strip(), latency, payload)


REGISTRY: dict[str, type[AsrProvider]] = {
    "openai-whisper": OpenAiWhisper,
    "gladia": Gladia,
    "huggingface": HuggingFaceModel,
    "elevenlabs": ElevenLabsScribe,
}


def build(names: list[str]) -> list[AsrProvider]:
    """Konstwi founisè yo epi kite sa ki pa gen kle deyò (ak yon avètisman)."""
    providers: list[AsrProvider] = []

    for name in names:
        cls = REGISTRY.get(name)
        if cls is None:
            raise SystemExit(f"Founisè enkoni: {name}. Chwa: {', '.join(REGISTRY)}")

        provider = cls()
        if not provider.available():
            print(f"  ⚠ {name}: pa gen kle API — n ap sote l")
            continue

        providers.append(provider)

    return providers
