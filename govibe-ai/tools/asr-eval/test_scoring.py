"""
Tès inite pou lojik notasyon an. Kouri: `python3 -m unittest discover tools/asr-eval`

Tès sa yo pa touche rezo — se lojik pi. Yo dwe vèt AVAN ou depanse yon santim
sou API founisè yo.
"""

import unittest

from kreyol import normalize, strip_accents
from scoring import ClipResult, breakdown, slot_matches, summarize, word_error_rate


class TestKreyolNormalization(unittest.TestCase):
    def test_contractions_are_expanded(self):
        self.assertEqual(normalize("m ap vini"), normalize("map vini"))
        self.assertEqual(normalize("m'ap vini"), normalize("mwen ap vini"))

    def test_joined_words(self):
        self.assertEqual(normalize("ki jan ou ye"), normalize("kijan ou ye"))
        self.assertEqual(normalize("pou ki sa"), normalize("poukisa"))

    def test_accents_are_not_errors(self):
        self.assertEqual(normalize("mèsi anpil"), normalize("mesi anpil"))
        self.assertEqual(strip_accents("kòman"), "koman")

    def test_number_words_become_digits(self):
        self.assertEqual(normalize("de poul"), "2 poul")
        self.assertEqual(normalize("senk diri"), "5 diri")
        # « sèt » ak « set » se menm chif la
        self.assertEqual(normalize("sèt"), normalize("set"))

    def test_real_words_are_not_split_into_contractions(self):
        # « poul » se yon manje, se pa « pou li ». « nap » se yon twal.
        self.assertEqual(normalize("de poul"), "2 poul")
        self.assertEqual(normalize("yon nap"), "1 nap")
        # Men vrè kontraksyon an ak espas la dwe toujou elaji.
        self.assertEqual(normalize("sa se pou l"), normalize("sa se pou li"))
        self.assertEqual(normalize("n ap vini"), normalize("nou ap vini"))

    def test_punctuation_is_dropped(self):
        self.assertEqual(normalize("Bonjou, kijan ou ye?"), normalize("bonjou kijan ou ye"))

    def test_empty_input(self):
        self.assertEqual(normalize(""), "")
        self.assertEqual(normalize("   "), "")


class TestWordErrorRate(unittest.TestCase):
    def test_perfect_transcript(self):
        self.assertEqual(word_error_rate("mwen vle de poul", "mwen vle de poul"), 0.0)

    def test_orthographic_variants_are_free(self):
        # Menm fraz, lòt òtograf — WER dwe 0, se pa yon erè ASR.
        self.assertEqual(word_error_rate("m ap vini", "mwen ap vini"), 0.0)

    def test_one_substitution(self):
        # 4 mo referans, 1 sibstitisyon = 0.25
        self.assertAlmostEqual(word_error_rate("mwen vle diri kole", "mwen vle diri blan"), 0.25)

    def test_empty_reference(self):
        self.assertEqual(word_error_rate("", ""), 0.0)
        self.assertEqual(word_error_rate("", "yon bagay"), 1.0)


class TestSlotMatching(unittest.TestCase):
    def test_number_word_matches_digit(self):
        self.assertTrue(slot_matches("2", "de"))
        self.assertTrue(slot_matches("de", "2"))

    def test_case_and_accent_insensitive(self):
        self.assertTrue(slot_matches("Poul", "poul"))
        self.assertTrue(slot_matches("griyo", "griyo"))

    def test_partial_match_counts(self):
        self.assertTrue(slot_matches("diri", "diri kole"))

    def test_wrong_value_fails(self):
        self.assertFalse(slot_matches("poul", "pwason"))
        self.assertFalse(slot_matches("2", "3"))

    def test_missing_value_fails(self):
        self.assertFalse(slot_matches("poul", ""))
        self.assertFalse(slot_matches("poul", None))


class TestClipResult(unittest.TestCase):
    def _clip(self, **kwargs) -> ClipResult:
        base = dict(
            clip="n001.opus",
            provider="test",
            expected_intent="order",
            predicted_intent="order",
            expected_slots={"item": "poul", "qty": "2"},
            predicted_slots={"item": "poul", "qty": "2"},
        )
        base.update(kwargs)
        return ClipResult(**base)

    def test_task_success_when_intent_and_slots_match(self):
        self.assertTrue(self._clip().task_success)

    def test_bad_transcript_can_still_succeed(self):
        # Se tèz santral la: transkripsyon an gen erè men entansyon an bon.
        clip = self._clip(
            reference="mwen ta renmen komande de poul ak diri",
            transcript="mwen ta renmen kòmandè de poul ak diri a",
        )
        self.assertGreater(clip.wer, 0.0)
        self.assertTrue(clip.task_success)

    def test_wrong_intent_fails(self):
        self.assertFalse(self._clip(predicted_intent="booking").task_success)

    def test_wrong_critical_slot_fails(self):
        clip = self._clip(predicted_slots={"item": "poul", "qty": "5"})
        self.assertTrue(clip.intent_ok)
        self.assertFalse(clip.slots_ok)
        self.assertFalse(clip.task_success)

    def test_non_critical_slot_does_not_block(self):
        clip = self._clip(
            expected_slots={"item": "poul", "qty": "2", "notes": "san pikliz"},
            predicted_slots={"item": "poul", "qty": "2"},
        )
        self.assertTrue(clip.task_success)
        self.assertAlmostEqual(clip.slot_accuracy, 2 / 3)

    def test_provider_error_fails(self):
        self.assertFalse(self._clip(error="timeout").task_success)

    def test_wer_is_none_without_reference(self):
        self.assertIsNone(self._clip().wer)


class TestSummary(unittest.TestCase):
    def _results(self, successes: int, total: int, provider="whisper") -> list[ClipResult]:
        out = []
        for i in range(total):
            ok = i < successes
            out.append(ClipResult(
                clip=f"n{i:03d}.opus",
                provider=provider,
                expected_intent="order",
                predicted_intent="order" if ok else "other",
                expected_slots={"item": "poul"},
                predicted_slots={"item": "poul"},
                latency_ms=1000,
            ))
        return out

    def test_verdict_go(self):
        summary = summarize(self._results(90, 100))["whisper"]
        self.assertAlmostEqual(summary.task_success_rate, 0.90)
        self.assertEqual(summary.verdict, "go")

    def test_verdict_needs_confirmation(self):
        self.assertEqual(summarize(self._results(75, 100))["whisper"].verdict, "go_with_confirmation")

    def test_verdict_no_go(self):
        self.assertEqual(summarize(self._results(60, 100))["whisper"].verdict, "no_go")

    def test_errors_are_counted_and_excluded_from_latency(self):
        results = self._results(5, 5)
        results.append(ClipResult(
            clip="bad.opus", provider="whisper", expected_intent="order", error="500",
        ))
        summary = summarize(results)["whisper"]
        self.assertEqual(summary.total, 6)
        self.assertEqual(summary.errors, 1)
        self.assertEqual(summary.avg_latency_ms, 1000)

    def test_breakdown_shows_where_it_breaks(self):
        # Tout siksè yo nan Lwès, tout echèk yo nan Nò — se konsa nou wè
        # si yon aksan patikilye ap kraze sistèm nan.
        results = self._results(2, 4)
        for result, region in zip(results, ["ouest", "ouest", "nord", "nord"]):
            result.meta = {"region": region}

        by_region = breakdown(results, "whisper", "region")
        self.assertEqual(by_region["ouest"], (2, 2))
        self.assertEqual(by_region["nord"], (0, 2))


if __name__ == "__main__":
    unittest.main()
