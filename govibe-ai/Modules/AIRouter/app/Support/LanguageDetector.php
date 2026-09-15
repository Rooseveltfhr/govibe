<?php

namespace Modules\AIRouter\Support;

/**
 * Détection de langue par marqueurs lexicaux, sur les quatre langues de la
 * plateforme. Classe pure, volontairement simple : elle sert à *orienter* le
 * routage (quel modèle gère bien cette langue), pas à faire de la
 * linguistique.
 *
 * Le créole haïtien partage beaucoup de vocabulaire avec le français ; ses
 * marqueurs exclusifs pèsent donc double, sans quoi tout créole serait
 * classé « français » et routé vers des modèles qui le maîtrisent mal.
 */
class LanguageDetector
{
    /** @var array<string, list<string>> marqueurs exclusifs (poids 2) */
    private const STRONG = [
        'ht' => ['mwen', 'nou', 'yo', 'kisa', 'kijan', 'poukisa', 'mèsi', 'tanpri', 'kounye', 'jodi',
            'anpil', 'granmoun', 'pitit', 'lajan', 'travay', 'konsa', 'sèlman', 'toujou', 'paske',
            'bagay', 'moun', 'vle', 'konnen', 'byenveni', 'bonjou', 'kòman', 'genyen', 'fèt'],
        'fr' => ['je', 'nous', 'vous', 'être', 'cette', 'bonjour', 'merci', 'pourquoi', 'comment',
            'aujourd', 'toujours', 'beaucoup', 'argent', 'travail', 'seulement', 'parce', 'choses'],
        'en' => ['the', 'you', 'and', 'are', 'with', 'that', 'what', 'please', 'hello', 'thanks',
            'because', 'always', 'money', 'work', 'people', 'want', 'know'],
        'es' => ['hola', 'gracias', 'usted', 'porque', 'siempre', 'dinero', 'trabajo', 'gente',
            'quiero', 'cómo', 'qué', 'muchas', 'esto', 'está'],
    ];

    /** @var array<string, list<string>> marqueurs fréquents mais partagés (poids 1) */
    private const WEAK = [
        'ht' => ['nan', 'pou', 'yon', 'se', 'ki', 'sa', 'epi', 'gen', 'fè', 'lè', 'avèk', 'ak', 'pa'],
        'fr' => ['le', 'la', 'les', 'des', 'est', 'une', 'pour', 'avec', 'que', 'qui', 'dans', 'sur'],
        'en' => ['is', 'a', 'to', 'of', 'in', 'for', 'it', 'on', 'we', 'i'],
        'es' => ['el', 'los', 'las', 'una', 'por', 'para', 'con', 'en', 'de', 'que'],
    ];

    /** @param list<string> $supported */
    public function __construct(private readonly array $supported = ['fr', 'ht', 'en', 'es']) {}

    /** Retourne le code de langue détecté, ou null si le texte ne dit rien. */
    public function detect(string $text): ?string
    {
        $words = $this->tokenize($text);

        if ($words === []) {
            return null;
        }

        $scores = array_fill_keys($this->supported, 0);

        foreach ($words as $word) {
            foreach ($this->supported as $language) {
                if (in_array($word, self::STRONG[$language] ?? [], true)) {
                    $scores[$language] += 2;
                }

                if (in_array($word, self::WEAK[$language] ?? [], true)) {
                    $scores[$language] += 1;
                }
            }
        }

        arsort($scores);

        /** @var string $best */
        $best = array_key_first($scores);

        return $scores[$best] > 0 ? $best : null;
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $parts = preg_split("/[^\p{L}]+/u", $normalized) ?: [];

        return array_values(array_filter($parts, static fn (string $w): bool => $w !== ''));
    }
}
