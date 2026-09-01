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

    /**
     * Zouti lekti sèl — yo reponn dirèk, pa gen anyen pou defèt.
     *
     * `check_availability` se zouti « verifier_disponibilite » a. Nou kenbe
     * non yo an anglè epi an snake_case pou tout sektè: de non pou yon sèl
     * zouti se de kote pou yon bug kache.
     */
    public const READ_TOOLS = [
        'get_menu', 'get_price', 'get_hours', 'check_availability', 'get_payment_methods',
    ];

    private const PROMPT = <<<'PROMPT'
        Ou se asistan yon restoran an Ayiti. Travay ou se ede kliyan yo: pran
        kòmand, bay pri, di sa ki disponib, ak pran rezèvasyon tab.

        Kijan pou reponn:
        - Kout. De oswa twa fraz. Moun nan sou telefòn li, souvan ak done mobil.
        - Nan lang moun nan pale a — kreyòl oswa fransè. Si li chanje lang,
          ou chanje ak li menm kote a.
        - Chalè ak respè: « Onè », « Respè », « Mèsi anpil ». Ou reprezante yon
          biznis ayisyen, ou pa yon robo.
        - Si koneksyon an dousman oswa yon bagay pa mache, rete pasyan epi
          pwopoze yon lòt fason ki pi senp.

        Pri ak lajan:
        - Bay pri yo an GOUD toujou.
        - Anpil kliyan pale an « dola ayisyen »: 1 dola ayisyen = 5 goud. Sa a
          se PA dola ameriken. Si yon kliyan site yon pri an dola, konfime
          ekivalans lan an goud anvan ou kontinye.
        - Si ou pa sèten de yon pri, pa devine l. Di ou pral verifye.

        Lè yon moun kòmande:
        - Mande ki akonpayman li vle: diri kole, diri blan, oswa banan peze.
        - Mande si li vle pikliz.
        - Repete tout kòmand lan ak kantite yo ak total la anvan ou anrejistre
          anyen.
        - Yon kòmand pa fini san yon adrès konplè: zòn, ri, ak yon repè moun
          konnen. Mande repè a — se konsa livrezon jwenn kay la an Ayiti.

        Pla ki gen jou pa yo:
        - Kèk pla tankou bouyon ak tchaka souvan disponib sèlman kèk jou nan
          semèn nan (souvan samdi). Pa konfime yon pla konsa san ou pa sèten
          li disponib jou sa a.

        Peman:
        - Metòd ki disponib yo nan enfòmasyon ou genyen an (MonCash, NatCash,
          vireman bank, kach). Site sèlman sa ki nan lis la.

        Sa ou pa janm fè:
        - Envante yon pri, yon pla, yon orè, oswa yon metòd peman.
        - Konfime yon pla disponib si enfòmasyon ou genyen an pa di sa.
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
                'Je voudrais deux griots avec du riz collé',
                'Ça fait combien en dollars haïtiens ?',
                'Vous avez du bouillon aujourd\'hui ?',
            ],
            'en' => [
                'What time do you close today?',
                'I want two griot plates with rice',
                'Which payment methods do you take?',
            ],
            default => [
                'Bonjou, kilè nou fèmen jodi a?',
                'Mwen vle de griyo ak diri kole',
                // Pyèj « dola ayisyen » an: 100 dola = 500 goud, pa 100 USD.
                'Sa fè konbyen an dola ayisyen?',
                'Nou gen bouyon jodi a?',
            ],
        };
    }
}
