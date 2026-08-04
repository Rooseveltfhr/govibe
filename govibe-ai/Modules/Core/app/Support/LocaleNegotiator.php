<?php

namespace Modules\Core\Support;

/**
 * Négociation de langue, logique pure (sans dépendance framework) pour rester
 * testable en unitaire strict. Ordre de priorité :
 * paramètre explicite → session → cookie → en-tête Accept-Language → défaut.
 */
class LocaleNegotiator
{
    /** @param list<string> $supported */
    public function __construct(
        private readonly array $supported,
        private readonly string $default,
    ) {}

    public function resolve(
        ?string $queryParam,
        ?string $session,
        ?string $cookie,
        ?string $acceptLanguageHeader,
    ): string {
        foreach ([$queryParam, $session, $cookie] as $candidate) {
            if ($candidate !== null && $this->isSupported($candidate)) {
                return strtolower($candidate);
            }
        }

        $fromHeader = $this->fromAcceptLanguage($acceptLanguageHeader ?? '');

        return $fromHeader ?? $this->default;
    }

    public function isSupported(string $locale): bool
    {
        return in_array(strtolower($locale), $this->supported, true);
    }

    /**
     * Parse un en-tête Accept-Language (ex. « ht, fr;q=0.8, en;q=0.5 ») et
     * retourne la première langue supportée par ordre de qualité décroissante.
     */
    public function fromAcceptLanguage(string $header): ?string
    {
        $candidates = [];

        foreach (explode(',', $header) as $part) {
            $segments = explode(';', trim($part));
            $tag = strtolower(trim($segments[0]));

            if ($tag === '') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($segments, 1) as $param) {
                if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m) === 1) {
                    $quality = (float) $m[1];
                }
            }

            // « fr-FR » compte pour « fr ».
            $primary = explode('-', $tag)[0];

            if ($this->isSupported($primary)) {
                $candidates[] = ['locale' => $primary, 'q' => $quality];
            }
        }

        usort($candidates, fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        return $candidates[0]['locale'] ?? null;
    }
}
