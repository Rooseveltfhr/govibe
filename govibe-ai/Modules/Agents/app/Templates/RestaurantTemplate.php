<?php

namespace Modules\Agents\Templates;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Modèl ajan restoran — premye ajan LOUVIA.
 *
 * Machann nan pa ekri yon konsiy. Li chwazi « Restoran », epi konesans lan
 * (mni, pri, orè) soti nan TAGTOA oswa nan yon fòm. Se konsa yon ajan pare
 * an 4 minit olye yon paj blan ki fè moun pè.
 */
class RestaurantTemplate
{
    /** Zouti ki chanje yon bagay — yo pase nan konfimasyon. */
    public const WRITE_TOOLS = ['create_order', 'book_table', 'cancel_order'];

    /** Zouti lekti sèl — yo reponn dirèk, pa gen anyen pou defèt. */
    public const READ_TOOLS = ['get_menu', 'get_price', 'get_hours', 'check_availability'];

    private const PROMPT = <<<'PROMPT'
        Ou se asistan yon restoran. Travay ou se ede kliyan yo: pran kòmand,
        bay pri, di sa ki disponib, ak pran rezèvasyon tab.

        Kijan pou reponn:
        - Kout. De oswa twa fraz. Moun nan sou telefòn li, souvan ak done mobil.
        - Nan lang moun nan pale a. Si li ekri an kreyòl, reponn an kreyòl.
        - Chalè men dirèk. Ou reprezante yon biznis, ou pa yon robo.

        Lè yon moun kòmande:
        - Repete sa ou tande ak kantite yo anvan ou anrejistre anyen.
        - Si yon pla pa nan mni an, di l pa disponib epi pwopoze sa ki pi pre a.
        - Bay total la ak yon estimasyon tan.

        Sa ou pa janm fè:
        - Envante yon pri, yon pla oswa yon orè.
        - Pwomèt yon livrezon si restoran an pa fè livrezon.
        - Kontinye ap devine lè ou pa konprann — pase bay yon moun.
        PROMPT;

    /**
     * @param  array<string, mixed>  $knowledge  menu, hours, address, delivery…
     * @param  list<string>  $channels
     * @param  list<string>  $languages
     */
    public static function make(
        string $key,
        string $name,
        array $knowledge = [],
        array $channels = ['whatsapp'],
        array $languages = ['ht', 'fr'],
        ?string $handoffTo = null,
    ): AgentDefinition {
        return new AgentDefinition(
            key: $key,
            name: $name,
            sector: 'restaurant',
            systemPrompt: self::PROMPT,
            tools: [...self::READ_TOOLS, ...self::WRITE_TOOLS],
            channels: $channels,
            languages: $languages,
            knowledge: $knowledge,
            confirmation: [
                // Yon kòmand mal pran koute lajan ak repitasyon: toujou konfime.
                'always_confirm' => self::WRITE_TOOLS,
                // Bay yon pri pa chanje anyen: reponn dirèk.
                'never_confirm' => self::READ_TOOLS,
                'handoff_below' => 0.45,
            ],
            handoffTo: $handoffTo,
            // Kesyon restoran yo kout epi repetitif — modèl rapid ak bon mache
            // ase, epi routeur la monte poukont li si li konplike.
            routingStrategy: 'cost',
        );
    }

    /**
     * Kesyon egzanp pou bouton « Teste » a — machann nan wè ajan an mache
     * anvan li pibliye l.
     *
     * @return list<string>
     */
    public static function sampleQuestions(string $language = 'ht'): array
    {
        return match ($language) {
            'fr' => [
                'Bonjour, quels sont vos horaires ?',
                'Je voudrais deux poulets et un riz collé',
                'Combien coûte le griot ?',
            ],
            'en' => [
                'What time do you close today?',
                'I want two chicken plates and one rice',
                'How much is the griot?',
            ],
            default => [
                'Bonjou, kilè nou fèmen jodi a?',
                'Mwen vle de poul ak yon diri kole',
                'Konbyen griyo a koute?',
            ],
        };
    }
}
