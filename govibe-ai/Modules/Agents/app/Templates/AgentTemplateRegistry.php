<?php

namespace Modules\Agents\Templates;

use InvalidArgumentException;
use Modules\Agents\DTO\AgentDefinition;

/**
 * Katalòg modèl ajan disponib — se sa ki ranpli lis « chwazi yon modèl »
 * kliyan an wè anvan li kreye ajan pa l.
 *
 * Menm patèn ak ProviderRegistry nan AIProvider: ajoute yon modèl sektè
 * nouvo se yon dosye Template + yon `register()`, zewo modifikasyon nan
 * rès sistèm nan (Ouvè/Fèmen). Klas la se yon enstans, pa yon rejis
 * estatik — konsa chak tès kòmanse ak yon katalòg pwòp li, san eta ki
 * kole ant tès.
 */
class AgentTemplateRegistry
{
    /** @var array<string, TemplateDescriptor> */
    private array $templates = [];

    public function __construct(bool $withDefaults = true)
    {
        if ($withDefaults) {
            $this->registerDefaults();
        }
    }

    /**
     * @param  callable(string, string, array<string, mixed>, list<string>, list<string>, ?string): AgentDefinition  $factory
     * @param  callable(string): list<string>  $sampleQuestions
     * @param  list<string>  $capabilities
     * @param  array<string, string>  $knowledgeFields
     */
    public function register(
        string $sector,
        string $label,
        string $description,
        callable $factory,
        callable $sampleQuestions,
        array $capabilities = [],
        string $kind = TemplateDescriptor::KIND_AGENT,
        array $knowledgeFields = [],
    ): void {
        $this->templates[$sector] = new TemplateDescriptor(
            $sector, $label, $description, $factory, $sampleQuestions,
            $capabilities, $kind, $knowledgeFields,
        );
    }

    /**
     * Katalòg la filtre pa kalite — se sa de blòk paj akèy la sèvi avè l.
     *
     * @return list<TemplateDescriptor>
     */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (TemplateDescriptor $t): bool => $t->kind === $kind,
        ));
    }

    public function has(string $sector): bool
    {
        return isset($this->templates[$sector]);
    }

    public function get(string $sector): ?TemplateDescriptor
    {
        return $this->templates[$sector] ?? null;
    }

    /** @return list<TemplateDescriptor> katalòg la, triye pa etikèt pou yon lis stab */
    public function all(): array
    {
        $all = array_values($this->templates);
        usort($all, static fn (TemplateDescriptor $a, TemplateDescriptor $b): int => $a->label <=> $b->label);

        return $all;
    }

    /**
     * Kreye yon ajan konkrè apati yon modèl — sa bouton « Kreye » a rele.
     *
     * @param  array<string, mixed>  $knowledge
     * @param  list<string>  $channels
     * @param  list<string>  $languages
     */
    public function make(
        string $sector,
        string $key,
        string $name,
        array $knowledge = [],
        array $channels = ['whatsapp'],
        array $languages = ['ht', 'fr'],
        ?string $handoffTo = null,
    ): AgentDefinition {
        $descriptor = $this->templates[$sector]
            ?? throw new InvalidArgumentException("Modèl sektè enkoni: {$sector}. Katalòg la genyen: ".implode(', ', array_keys($this->templates)));

        return ($descriptor->factory)($key, $name, $knowledge, $channels, $languages, $handoffTo);
    }

    /**
     * Kesyon egzanp pou bouton « Demo » a.
     *
     * @return list<string>
     */
    public function sampleQuestions(string $sector, string $language = 'ht'): array
    {
        $descriptor = $this->templates[$sector]
            ?? throw new InvalidArgumentException("Modèl sektè enkoni: {$sector}");

        return ($descriptor->sampleQuestions)($language);
    }

    private function registerDefaults(): void
    {
        $this->register(
            sector: 'restaurant',
            label: 'Restoran',
            description: 'Reponn kliyan sou WhatsApp: mni, pri, akonpayman, kòmand.',
            factory: RestaurantTemplate::make(...),
            sampleQuestions: RestaurantTemplate::sampleQuestions(...),
            capabilities: [
                'Bay pri an goud, epi konvèti an dola ayisyen',
                'Pwopoze akonpayman ak pikliz anvan li valide',
                'Mande adrès ak yon repè anvan yon livrezon',
                'Konfime chak kòmand anvan li anrejistre l',
            ],
            knowledgeFields: [
                'Menu' => 'menu', 'Horaires' => 'horaires', 'Adresse' => 'adresse',
                'Livraison' => 'livraison', 'Paiement' => 'paiement',
            ],
        );

        $this->register(
            sector: 'clinic',
            label: 'Klinik / Sante',
            description: 'Randevou, sèvis, tarif — zewo konsèy medikal.',
            factory: ClinicTemplate::make(...),
            sampleQuestions: ClinicTemplate::sampleQuestions(...),
            capabilities: [
                'Bay sèvis, orè ak tarif klinik la sèlman',
                'Pran randevou apre yon konfimasyon',
                'Pa janm bay dyagnostik ni preskripsyon',
                'Pase bay yon moun lè li pa sèten',
            ],
            knowledgeFields: [
                'Services' => 'services', 'Horaires' => 'horaires',
                'Adresse' => 'adresse', 'Tarifs' => 'tarifs',
            ],
        );

        $this->register(
            sector: 'school',
            label: 'Lekòl',
            description: 'Enskripsyon, pwogram, frè, randevou ak direksyon an.',
            factory: SchoolTemplate::make(...),
            sampleQuestions: SchoolTemplate::sampleQuestions(...),
            capabilities: [
                'Eksplike pwogram ak kondisyon enskripsyon',
                'Bay frè lekòl la jan direksyon an mete yo',
                'Pran randevou ak direksyon an',
                'Pa janm envante yon frè ni yon dat',
            ],
            knowledgeFields: [
                'Programmes' => 'programmes', 'Horaires' => 'horaires',
                'Adresse' => 'adresse', 'Frais' => 'frais',
            ],
        );

        $this->registerChatbots();
    }

    /**
     * Chatbot yo: yo reponn, yo pa aji. Yo pataje menm baz konsiy lan; sa
     * ki chanje se misyon an ak chan konesans lan.
     */
    private function registerChatbots(): void
    {
        $this->register(
            sector: 'support',
            label: 'Sipò kliyan',
            description: 'Reponn kesyon kliyan yo poze pi souvan, 24h sou 24.',
            factory: ChatbotTemplate::maker('support', 'Ou se asistan sipò yon biznis. Travay ou se reponn kesyon kliyan yo poze pi souvan.'),
            sampleQuestions: ChatbotTemplate::sampleQuestions(...),
            capabilities: [
                'Reponn kesyon ki repete yo, san fatig',
                'Bay orè, adrès, pri ak règleman biznis lan',
                'Di klèman lè li pa konnen',
                'Pase kliyan an bay yon moun lè sa nesesè',
            ],
            kind: TemplateDescriptor::KIND_CHATBOT,
            knowledgeFields: [
                'Questions fréquentes' => 'faq', 'Horaires' => 'horaires',
                'Adresse' => 'adresse', 'Contact' => 'contact',
            ],
        );

        $this->register(
            sector: 'website',
            label: 'Asistan sit entènèt',
            description: 'Yon bwat chat sou sit ou: li akeyi vizitè epi li reponn.',
            factory: ChatbotTemplate::maker('website', 'Ou se asistan ki sou sit entènèt yon biznis. Travay ou se akeyi vizitè yo epi reponn kesyon yo sou sa biznis lan ofri.'),
            sampleQuestions: ChatbotTemplate::sampleQuestions(...),
            capabilities: [
                'Akeyi chak vizitè sou sit la',
                'Eksplike sèvis ak pri yo',
                'Kolekte kesyon ki mande yon moun',
                'Travay nan kat lang: kreyòl, franse, anglè, panyòl',
            ],
            kind: TemplateDescriptor::KIND_CHATBOT,
            knowledgeFields: [
                'Services' => 'services', 'Tarifs' => 'tarifs',
                'Horaires' => 'horaires', 'Contact' => 'contact',
            ],
        );

        $this->register(
            sector: 'whatsapp',
            label: 'Akèy WhatsApp',
            description: 'Premye repons lan sou WhatsApp, menm lè ou okipe.',
            factory: ChatbotTemplate::maker('whatsapp', 'Ou se premye repons yon biznis sou WhatsApp. Travay ou se akeyi moun nan touswit epi reponn kesyon senp yo.'),
            sampleQuestions: ChatbotTemplate::sampleQuestions(...),
            capabilities: [
                'Reponn touswit, menm lè biznis lan fèmen',
                'Bay enfòmasyon debaz yo san tann',
                'Prepare mesaj la pou moun k ap pran l apre a',
                'Pa janm pwomèt yon bagay li pa ka fè',
            ],
            kind: TemplateDescriptor::KIND_CHATBOT,
            knowledgeFields: [
                'Message de bienvenue' => 'bienvenue', 'Services' => 'services',
                'Horaires' => 'horaires', 'Contact' => 'contact',
            ],
        );
    }
}
