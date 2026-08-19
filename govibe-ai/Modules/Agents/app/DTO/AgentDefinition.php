<?php

namespace Modules\Agents\DTO;

/**
 * Yon ajan, jan runtime lan wè l.
 *
 * Se yon sèl objè pou tout kanal: menm ajan an reponn sou WhatsApp, sou
 * telefòn ak sou widget la. Kanal la se yon pòt, se pa yon ajan apa.
 */
final readonly class AgentDefinition
{
    /**
     * @param  string  $sector  'restaurant', 'clinic', 'school'…
     * @param  list<string>  $tools  zouti ajan an gen dwa sèvi
     * @param  list<string>  $channels  'whatsapp', 'phone', 'web'
     * @param  list<string>  $languages  lang ajan an pale, premye a se defo a
     * @param  array<string, mixed>  $knowledge  sa ajan an konnen (mni, orè, pri…)
     * @param  array<string, mixed>  $confirmation  konfigirasyon ConfirmationPolicy
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $sector,
        public string $systemPrompt = '',
        public array $tools = [],
        public array $channels = ['whatsapp'],
        public array $languages = ['ht', 'fr'],
        public array $knowledge = [],
        public array $confirmation = [],
        public ?string $handoffTo = null,
        public string $routingStrategy = 'balanced',
    ) {}

    public function speaks(string $language): bool
    {
        return in_array($language, $this->languages, true);
    }

    public function defaultLanguage(): string
    {
        return $this->languages[0] ?? 'ht';
    }

    public function hasTool(string $tool): bool
    {
        return in_array($tool, $this->tools, true);
    }

    public function servesChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * Konsiy konplè a: konsiy sektè a + konesans biznis lan.
     * Se sa runtime lan voye bay modèl la.
     */
    public function compiledPrompt(string $language): string
    {
        $parts = [$this->systemPrompt];

        $parts[] = sprintf(
            'Ou se asistan %s. Reponn nan lang moun nan ekri a (defo: %s).',
            $this->name,
            $language,
        );

        foreach ($this->knowledge as $label => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = ucfirst((string) $label).': '.$value;
            }
        }

        // Règ ki pwoteje repitasyon platfòm nan — yo pa opsyonèl.
        $parts[] = 'Pa janm envante yon pri, yon pla oswa yon orè ki pa nan enfòmasyon anwo a.';
        $parts[] = 'Si ou pa sèten, di ou pa sèten epi pwopoze pou pase bay yon moun.';

        return implode("\n", array_filter($parts, static fn (string $p): bool => trim($p) !== ''));
    }
}
