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
     */
    public function register(
        string $sector,
        string $label,
        string $description,
        callable $factory,
        callable $sampleQuestions,
        array $capabilities = [],
    ): void {
        $this->templates[$sector] = new TemplateDescriptor(
            $sector, $label, $description, $factory, $sampleQuestions, $capabilities,
        );
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
        );
    }
}
